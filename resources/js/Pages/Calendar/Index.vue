<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    calendars: Array,
    events: Array,
    members: Array,
    defaults: Object,
    filters: Object,
    flash: Object,
});

const showForm = ref(false);
const selectedCalendars = ref(props.filters.calendars?.length ? props.filters.calendars : props.calendars.map((c) => c.id));
const category = ref(props.filters.category || '');
const selectedAttendees = ref([]);
const attendeeEntries = ref([]);
const editingId = ref(null);
const viewMode = ref('list'); // list | week | day | month
const today = new Date();

const fmtDate = new Intl.DateTimeFormat(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
});
const fmtTime = new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
});

const formatRange = (event) => {
    const start = new Date(event.start_at);
    const end = new Date(event.end_at);

    if (event.is_all_day) {
        return `${fmtDate.format(start)} · All day`;
    }

    const sameDay = start.toDateString() === end.toDateString();
    if (sameDay) {
        return `${fmtDate.format(start)} · ${fmtTime.format(start)} → ${fmtTime.format(end)}`;
    }
    return `${fmtDate.format(start)} ${fmtTime.format(start)} → ${fmtDate.format(end)} ${fmtTime.format(end)}`;
};

const reminderLabel = (event) => {
    const firstAtt = event.attendees?.[0];
    const minutes = firstAtt?.reminder_offset_minutes ?? quickForm.reminder_offset_minutes ?? null;
    if (!minutes) return '';
    if (minutes >= 60) {
        const hrs = minutes / 60;
        return `${hrs}h before`;
    }
    return `${minutes}m before`;
};

const viewLabel = computed(() => {
    if (viewMode.value === 'day') {
        return `Today · ${fmtDate.format(today)}`;
    }
    if (viewMode.value === 'week') {
        const end = new Date();
        end.setDate(end.getDate() + 7);
        return `Next 7 days · ${fmtDate.format(today)} - ${fmtDate.format(end)}`;
    }
    if (viewMode.value === 'month') {
        const monthName = today.toLocaleString(undefined, { month: 'long', year: 'numeric' });
        return `Month · ${monthName}`;
    }
    return 'Upcoming events';
});

const form = useForm({
    calendar_id: props.defaults.calendarId,
    title: '',
    description: '',
    location: '',
    start_at: props.defaults.start,
    end_at: props.defaults.end,
    is_all_day: false,
    visibility: 'household',
    category: '',
    attendees: [],
    reminder_offset_minutes: 60,
});

const quickForm = useForm({
    calendar_id: props.defaults.calendarId,
    title: '',
    start_at: props.defaults.start,
    end_at: props.defaults.end,
    is_all_day: false,
    visibility: 'household',
    category: '',
    reminder_offset_minutes: 60,
    attendees: [],
    description: '',
    location: '',
});

const filteredEvents = computed(() =>
    props.events.filter((event) => {
        const calendarOk = selectedCalendars.value.includes(event.calendar_id);
        const categoryOk = category.value ? event.category === category.value : true;
        return calendarOk && categoryOk;
    }),
);

const displayedEvents = computed(() => {
    if (viewMode.value === 'day') {
        const today = new Date();
        const dayKey = today.toISOString().split('T')[0];
        return filteredEvents.value.filter((e) => e.start_at.startsWith(dayKey));
    }
    if (viewMode.value === 'week') {
        const start = new Date();
        const end = new Date();
        end.setDate(end.getDate() + 7);
        return filteredEvents.value.filter((e) => {
            const d = new Date(e.start_at);
            return d >= start && d <= end;
        });
    }
    return filteredEvents.value;
});

const hours = Array.from({ length: 16 }, (_, i) => i + 6); // 6 AM - 21
const weekDays = computed(() => {
    const start = new Date();
    return Array.from({ length: 7 }, (_, idx) => {
        const d = new Date(start);
        d.setDate(start.getDate() + idx);
        return {
            key: d.toISOString().split('T')[0],
            label: fmtDate.format(d),
        };
    });
});

const eventsByHour = computed(() => {
    if (viewMode.value !== 'day') return [];
    const buckets = {};
    displayedEvents.value.forEach((event) => {
        const start = new Date(event.start_at);
        const hour = start.getHours();
        buckets[hour] = buckets[hour] || [];
        buckets[hour].push(event);
    });
    return hours.map((h) => ({ hour: h, items: buckets[h] || [] }));
});

const eventsByDay = computed(() => {
    const grouped = {};
    displayedEvents.value.forEach((event) => {
        const day = event.start_at.split('T')[0];
        grouped[day] = grouped[day] || [];
        grouped[day].push(event);
    });
    // add conflict flags within each day
    Object.values(grouped).forEach((list) => {
        list.sort((a, b) => new Date(a.start_at) - new Date(b.start_at));
        for (let i = 0; i < list.length; i++) {
            const current = list[i];
            const currentStart = new Date(current.start_at).getTime();
            const currentEnd = new Date(current.end_at).getTime();
            current.conflict = false;
            for (let j = 0; j < list.length; j++) {
                if (i === j) continue;
                const other = list[j];
                const otherStart = new Date(other.start_at).getTime();
                const otherEnd = new Date(other.end_at).getTime();
                const overlap = currentStart < otherEnd && otherStart < currentEnd;
                if (overlap) {
                    current.conflict = true;
                    break;
                }
            }
        }
    });
    return Object.entries(grouped).sort(([a], [b]) => (a > b ? 1 : -1));
});

const eventsByWeekDay = computed(() => {
    if (viewMode.value !== 'week') return [];
    const map = {};
    displayedEvents.value.forEach((event) => {
        const key = event.start_at.split('T')[0];
        map[key] = map[key] || [];
        map[key].push(event);
    });
    Object.values(map).forEach((list) => {
        list.sort((a, b) => new Date(a.start_at) - new Date(b.start_at));
        list.forEach((ev, i) => {
            const start = new Date(ev.start_at).getTime();
            const end = new Date(ev.end_at).getTime();
            ev.conflict = list.some((other, j) => {
                if (i === j) return false;
                const os = new Date(other.start_at).getTime();
                const oe = new Date(other.end_at).getTime();
                return start < oe && os < end;
            });
        });
    });
    return weekDays.value.map((day) => ({
        ...day,
        items: map[day.key] || [],
    }));
});

const monthGrid = computed(() => {
    if (viewMode.value !== 'month') return [];
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth(), 1);
    const start = new Date(first);
    start.setDate(first.getDate() - first.getDay()); // back to Sunday
    const weeks = [];
    const map = {};
    displayedEvents.value.forEach((event) => {
        const key = event.start_at.split('T')[0];
        map[key] = map[key] || [];
        map[key].push(event);
    });
    for (let w = 0; w < 6; w++) {
        const days = [];
        for (let d = 0; d < 7; d++) {
            const cellDate = new Date(start);
            cellDate.setDate(start.getDate() + w * 7 + d);
            const key = cellDate.toISOString().split('T')[0];
            const isCurrentMonth = cellDate.getMonth() === now.getMonth();
            days.push({
                key,
                label: cellDate.getDate(),
                isCurrentMonth,
                events: map[key] || [],
            });
        }
        weeks.push(days);
    }
    return weeks;
});

const submit = () => {
    form.attendees = attendeeEntries.value.map((a) => ({
        user_id: a.user_id,
        reminder_offset_minutes: a.reminder_offset_minutes,
    }));

    const payload = {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'description', 'location', 'is_all_day', 'category', 'attendees', 'reminder_offset_minutes');
            showForm.value = false;
            editingId.value = null;
            attendeeEntries.value = [];
        },
    };

    if (editingId.value) {
        form.patch(route('calendar.events.update', editingId.value), payload);
    } else {
        form.post(route('calendar.events.store'), payload);
    }
};

const deleteEvent = (id) => {
    if (!confirm('Delete this event?')) return;
    router.delete(route('calendar.events.destroy', id), { preserveScroll: true });
};

const applyFilters = () => {
    router.get(
        route('calendar.index'),
        {
            calendars: selectedCalendars.value,
            category: category.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const quickAdd = () => {
    if (!quickForm.title) return;
    quickForm.post(route('calendar.events.store'), {
        preserveScroll: true,
        onSuccess: () => {
            quickForm.reset('title', 'description', 'location', 'category');
        },
    });
};

const isSelected = (memberId) => attendeeEntries.value.some((a) => a.user_id === memberId);

const toggleAttendee = (memberId) => {
    const idx = attendeeEntries.value.findIndex((a) => a.user_id === memberId);
    if (idx >= 0) {
        attendeeEntries.value.splice(idx, 1);
    } else {
        attendeeEntries.value.push({
            user_id: memberId,
            reminder_offset_minutes: form.reminder_offset_minutes ?? 60,
        });
    }
};

const updateAttendeeReminder = (memberId, value) => {
    const idx = attendeeEntries.value.findIndex((a) => a.user_id === memberId);
    const minutes = value ? Number(value) : null;
    if (idx >= 0) {
        attendeeEntries.value[idx].reminder_offset_minutes = minutes;
    } else {
        attendeeEntries.value.push({ user_id: memberId, reminder_offset_minutes: minutes });
    }
};

const startEdit = (event) => {
    editingId.value = event.id;
    showForm.value = true;
    form.calendar_id = event.calendar_id;
    form.title = event.title;
    form.description = event.description || '';
    form.location = event.location || '';
    form.start_at = event.start_at;
    form.end_at = event.end_at;
    form.is_all_day = event.is_all_day;
    form.visibility = event.visibility || 'household';
    form.category = event.category || '';
    attendeeEntries.value = (event.attendees || []).map((a) => ({
        user_id: a.user_id || a.id,
        reminder_offset_minutes: a.reminder_offset_minutes ?? form.reminder_offset_minutes ?? 60,
    }));
};

const cancelEdit = () => {
    editingId.value = null;
    showForm.value = false;
    form.reset('title', 'description', 'location', 'is_all_day', 'category', 'attendees');
    form.calendar_id = props.defaults.calendarId;
    form.start_at = props.defaults.start;
    form.end_at = props.defaults.end;
    form.visibility = 'household';
    attendeeEntries.value = [];
};
</script>

<template>
    <Head title="Calendar" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-slate-800">Family Calendar</h2>
                <PrimaryButton @click="showForm = !showForm">
                    {{ showForm ? 'Close' : 'Add Event' }}
                </PrimaryButton>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto space-y-6">
                <div class="bg-gradient-to-r from-teal-50 via-frost-50 to-indigo-50 border border-indigo-100 shadow rounded-lg p-4 flex flex-wrap gap-3 items-center justify-between">
                    <div class="flex items-center gap-3 flex-wrap">
                        <InputLabel class="text-slate-700" value="Quick add" />
                        <TextInput
                            class="w-56"
                            placeholder="Title"
                            v-model="quickForm.title"
                        />
                        <TextInput type="datetime-local" class="w-52" v-model="quickForm.start_at" />
                        <TextInput type="datetime-local" class="w-52" v-model="quickForm.end_at" />
                        <select
                            v-model="quickForm.calendar_id"
                            class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="cal in calendars" :key="cal.id" :value="cal.id">
                                {{ cal.name }}
                            </option>
                        </select>
                        <select
                            v-model.number="quickForm.reminder_offset_minutes"
                            class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option :value="15">15m</option>
                            <option :value="30">30m</option>
                            <option :value="60">1h</option>
                            <option :value="120">2h</option>
                        </select>
                        <PrimaryButton :disabled="quickForm.processing" @click="quickAdd">Add</PrimaryButton>
                    </div>
                    <div class="text-xs text-slate-500">
                        Default visibility household · reminders apply to all attendees
                    </div>
                </div>
                <div class="bg-white shadow rounded-lg p-4 flex flex-wrap gap-4 items-center justify-between">
                    <div class="flex flex-wrap gap-3">
                        <div class="flex items-center gap-2" v-for="cal in calendars" :key="cal.id">
                            <input
                                type="checkbox"
                                :id="`cal-${cal.id}`"
                                :value="cal.id"
                                v-model="selectedCalendars"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                @change="applyFilters"
                            />
                            <label :for="`cal-${cal.id}`" class="flex items-center gap-2 text-sm text-slate-700">
                                <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: cal.color || '#14b8a6' }"></span>
                                {{ cal.name }}
                            </label>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <label for="category" class="text-sm text-slate-700">Category</label>
                        <select
                            id="category"
                            v-model="category"
                            @change="applyFilters"
                            class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All</option>
                            <option value="school">School</option>
                            <option value="work">Work</option>
                            <option value="medical">Medical</option>
                            <option value="errand">Errand</option>
                        </select>
                        <div class="flex gap-2 ml-4">
                            <button
                                type="button"
                                class="text-sm px-3 py-1 rounded-full border"
                                :class="viewMode === 'list' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 text-slate-700'"
                                @click="viewMode = 'list'"
                            >
                                List
                            </button>
                            <button
                                type="button"
                                class="text-sm px-3 py-1 rounded-full border"
                                :class="viewMode === 'month' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 text-slate-700'"
                                @click="viewMode = 'month'"
                            >
                                Month
                            </button>
                            <button
                                type="button"
                                class="text-sm px-3 py-1 rounded-full border"
                                :class="viewMode === 'week' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 text-slate-700'"
                                @click="viewMode = 'week'"
                            >
                                Week
                            </button>
                            <button
                                type="button"
                                class="text-sm px-3 py-1 rounded-full border"
                                :class="viewMode === 'day' ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 text-slate-700'"
                                @click="viewMode = 'day'"
                            >
                                Day
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="showForm" class="bg-white shadow rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-lg font-semibold text-slate-800">
                            {{ editingId ? 'Edit Event' : 'Add Event' }}
                        </div>
                        <SecondaryButton type="button" @click="cancelEdit">Close</SecondaryButton>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="title" value="Title" />
                            <TextInput id="title" v-model="form.title" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="form.errors.title" />
                        </div>
                        <div>
                            <InputLabel for="calendar_id" value="Calendar" />
                            <select id="calendar_id" v-model="form.calendar_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option v-for="cal in calendars" :key="cal.id" :value="cal.id">
                                    {{ cal.name }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.calendar_id" />
                        </div>
                        <div>
                            <InputLabel for="start_at" value="Starts" />
                            <TextInput id="start_at" type="datetime-local" v-model="form.start_at" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="form.errors.start_at" />
                        </div>
                        <div>
                            <InputLabel for="end_at" value="Ends" />
                            <TextInput id="end_at" type="datetime-local" v-model="form.end_at" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="form.errors.end_at" />
                        </div>
                        <div class="md:col-span-2 flex items-center space-x-3">
                            <Checkbox id="is_all_day" v-model:checked="form.is_all_day" />
                            <InputLabel for="is_all_day" value="All-day" class="mb-0" />
                        </div>
                        <div>
                            <InputLabel for="visibility" value="Visibility" />
                            <select id="visibility" v-model="form.visibility" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="household">Household</option>
                                <option value="attendees">Attendees</option>
                                <option value="private">Private</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.visibility" />
                        </div>
                        <div>
                            <InputLabel for="reminder_offset_minutes" value="Reminder" />
                            <select
                                id="reminder_offset_minutes"
                                v-model.number="form.reminder_offset_minutes"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option :value="null">Default</option>
                                <option :value="15">15m</option>
                                <option :value="30">30m</option>
                                <option :value="60">1h</option>
                                <option :value="120">2h</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.reminder_offset_minutes" />
                        </div>
                        <div>
                            <InputLabel for="category" value="Category" />
                            <TextInput id="category" v-model="form.category" class="mt-1 block w-full" placeholder="school / work / medical" />
                            <InputError class="mt-1" :message="form.errors.category" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="location" value="Location" />
                            <TextInput id="location" v-model="form.location" class="mt-1 block w-full" placeholder="Where" />
                            <InputError class="mt-1" :message="form.errors.location" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Attendees" />
                            <div class="flex flex-wrap gap-3 mt-2">
                                <div
                                    v-for="member in members"
                                    :key="member.id"
                                    class="flex items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-sm shadow-sm"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        :checked="isSelected(member.id)"
                                        @change="toggleAttendee(member.id)"
                                    />
                                    <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: member.avatar_color || '#14b8a6' }"></span>
                                    <span class="text-slate-700">{{ member.name }}</span>
                                    <input
                                        type="number"
                                        min="0"
                                        step="5"
                                        class="w-16 ml-2 rounded-md border-slate-200 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="m"
                                        :value="attendeeEntries.find((a) => a.user_id === member.id)?.reminder_offset_minutes ?? ''"
                                        @input="updateAttendeeReminder(member.id, $event.target.value)"
                                        :disabled="!isSelected(member.id)"
                                    />
                                    <span class="text-[11px] text-slate-500">m before</span>
                                </div>
                            </div>
                            <InputError class="mt-1" :message="form.errors.attendees" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="description" value="Notes" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Optional details"
                            />
                            <InputError class="mt-1" :message="form.errors.description" />
                        </div>
                    </div>
                    <div class="mt-6 flex items-center space-x-3">
                        <PrimaryButton :disabled="form.processing" @click="submit">
                            {{ editingId ? 'Update event' : 'Save event' }}
                        </PrimaryButton>
                        <SecondaryButton type="button" @click="cancelEdit">Cancel</SecondaryButton>
                        <span v-if="form.recentlySuccessful" class="text-sm text-green-600">Saved</span>
                    </div>
                </div>

                <div class="bg-white shadow rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-slate-800">{{ viewLabel }}</h3>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="cal in calendars" :key="cal.id" class="flex items-center gap-2 text-sm">
                                <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: cal.color || '#14b8a6' }"></span>
                                <span class="text-slate-700">{{ cal.name }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="viewMode === 'month'" class="space-y-2">
                        <div class="grid grid-cols-7 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            <div class="py-2 text-center" v-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day">
                                {{ day }}
                            </div>
                        </div>
                        <div class="grid grid-cols-7 gap-2">
                            <div
                                v-for="(week, wi) in monthGrid"
                                :key="`week-${wi}`"
                                class="contents"
                            >
                                <div
                                    v-for="cell in week"
                                    :key="cell.key"
                                    class="min-h-28 border border-slate-200 rounded-lg p-2 flex flex-col gap-2 bg-white"
                                    :class="cell.isCurrentMonth ? '' : 'bg-slate-50 text-slate-400'"
                                >
                                    <div class="text-xs font-semibold text-slate-700 flex items-center justify-between gap-2">
                                        <span class="flex items-center gap-2">
                                            <span class="h-2 w-2 rounded-full" :class="cell.isCurrentMonth ? 'bg-indigo-200' : 'bg-slate-300'"></span>
                                            {{ cell.label }}
                                        </span>
                                        <span v-if="cell.events.length" class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                                            {{ cell.events.length }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <div
                                            v-for="event in cell.events"
                                            :key="event.id"
                                            class="inline-flex items-center gap-2 px-2 py-1 rounded-md border border-slate-200 text-xs bg-gradient-to-r from-white to-indigo-50"
                                        >
                                            <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: event.calendar?.color || '#14b8a6' }"></span>
                                            <span class="font-semibold text-slate-800 truncate">{{ event.title }}</span>
                                            <span v-if="event.attendees?.length" class="flex -space-x-1">
                                                <span
                                                    v-for="att in event.attendees.slice(0,3)"
                                                    :key="att.id"
                                                    class="h-5 w-5 rounded-full border border-white text-[10px] bg-slate-200 flex items-center justify-center"
                                                    :style="{ backgroundColor: att.user?.avatar_color || '#cbd5e1', color: '#0f172a' }"
                                                >
                                                    {{ att.user?.name?.charAt(0)?.toUpperCase() || 'A' }}
                                                </span>
                                                <span v-if="event.attendees.length > 3" class="text-[10px] text-slate-500 px-1">+{{ event.attendees.length - 3 }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="viewMode === 'week'" class="space-y-3">
                        <div v-if="eventsByWeekDay.every((d) => d.items.length === 0)" class="text-slate-500 text-sm bg-gradient-to-r from-slate-50 to-indigo-50 border border-indigo-100 rounded-lg p-4">
                            No events this week. Add one to get started.
                        </div>
                        <div class="grid grid-cols-7 gap-3">
                            <div
                                v-for="day in eventsByWeekDay"
                                :key="day.key"
                                class="border border-slate-200 rounded-lg p-2 bg-white flex flex-col gap-2 min-h-40"
                            >
                                <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                                    <span>{{ day.label }}</span>
                                    <span v-if="day.items.length" class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 text-[11px]">{{ day.items.length }}</span>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <div
                                        v-for="event in day.items"
                                        :key="event.id"
                                        class="rounded-md border border-slate-200 bg-gradient-to-r from-white to-indigo-50 px-2 py-1 text-xs flex flex-col gap-1 shadow-sm"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: event.calendar?.color || '#14b8a6' }"></span>
                                            <span class="font-semibold text-slate-800 truncate">{{ event.title }}</span>
                                            <span v-if="event.conflict" class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Conflict</span>
                                        </div>
                                        <div class="text-slate-600">
                                            {{ formatRange(event) }}
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            <span v-if="event.category" class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ event.category }}</span>
                                            <span v-if="reminderLabel(event)" class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">{{ reminderLabel(event) }}</span>
                                        </div>
                                        <div v-if="event.attendees?.length" class="flex flex-wrap gap-1">
                                            <span
                                                v-for="att in event.attendees.slice(0,3)"
                                                :key="att.id"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-slate-200 text-[11px] text-slate-700"
                                            >
                                                <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: att.user?.avatar_color || '#14b8a6' }"></span>
                                                {{ att.user?.name || 'Member' }}
                                            </span>
                                            <span v-if="event.attendees.length > 3" class="text-[11px] text-slate-500 px-1">+{{ event.attendees.length - 3 }}</span>
                                        </div>
                                        <div class="flex gap-2 text-[11px]">
                                            <button class="text-indigo-600 hover:text-indigo-700" type="button" @click="startEdit(event)">Edit</button>
                                            <button class="text-rose-600 hover:text-rose-700" type="button" @click="deleteEvent(event.id)">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="viewMode === 'day'" class="space-y-3">
                        <div v-if="eventsByDay.length === 0" class="text-slate-500 text-sm bg-gradient-to-r from-slate-50 to-indigo-50 border border-indigo-100 rounded-lg p-4">
                            No events today. Add one to get started.
                        </div>
                        <div class="border border-slate-200 rounded-lg overflow-hidden">
                            <div v-for="slot in eventsByHour" :key="slot.hour" class="flex border-b last:border-b-0 border-slate-100">
                                <div class="w-24 px-3 py-3 text-sm text-slate-500 bg-slate-50 border-r border-slate-100">
                                    {{ slot.hour.toString().padStart(2, '0') }}:00
                                </div>
                                <div class="flex-1 px-3 py-2 space-y-2">
                                    <div
                                        v-for="event in slot.items"
                                        :key="event.id"
                                        class="rounded-lg border border-slate-200 bg-gradient-to-r from-white to-indigo-50 px-3 py-2 shadow-sm flex items-start justify-between"
                                    >
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: event.calendar?.color || '#14b8a6' }"></span>
                                                <div class="font-semibold text-slate-800">{{ event.title }}</div>
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                                                    {{ event.calendar?.name || 'Calendar' }}
                                                </span>
                                                <span v-if="event.category" class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                                                    {{ event.category }}
                                                </span>
                                                <span v-if="event.conflict" class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                                    Conflict
                                                </span>
                                            </div>
                                            <div class="text-sm text-slate-600">
                                                {{ formatRange(event) }}
                                                <span v-if="event.location">· {{ event.location }}</span>
                                                <span v-if="reminderLabel(event)" class="ml-2 text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full">
                                                    {{ reminderLabel(event) }}
                                                </span>
                                            </div>
                                            <div v-if="event.attendees?.length" class="flex flex-wrap gap-2">
                                                <span
                                                    v-for="att in event.attendees"
                                                    :key="att.id"
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full border border-slate-200 text-slate-700"
                                                >
                                                    <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: att.user?.avatar_color || '#14b8a6' }"></span>
                                                    {{ att.user?.name || 'Member' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex gap-3 text-sm">
                                            <button
                                                type="button"
                                                class="text-indigo-600 hover:text-indigo-700"
                                                @click="startEdit(event)"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                class="text-rose-600 hover:text-rose-700"
                                                @click="deleteEvent(event.id)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else>
                        <div v-if="eventsByDay.length === 0" class="text-slate-500 text-sm bg-gradient-to-r from-slate-50 to-indigo-50 border border-indigo-100 rounded-lg p-4">
                            No events in this view. Try switching the filter or add an event to get started.
                        </div>
                        <div v-for="[day, list] in eventsByDay" :key="day" class="mb-6 last:mb-0">
                            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ day }}</div>
                            <div class="space-y-3">
                                <div
                                    v-for="event in list"
                                    :key="event.id"
                                    class="border border-slate-200 rounded-lg p-4 flex items-start justify-between bg-gradient-to-r from-white to-slate-50"
                                >
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: event.calendar?.color || '#14b8a6' }"></span>
                                            <div class="font-semibold text-slate-800">{{ event.title }}</div>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                                                {{ event.calendar?.name || 'Calendar' }}
                                            </span>
                                            <span v-if="event.category" class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                                                {{ event.category }}
                                            </span>
                                            <span v-if="event.conflict" class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                                Conflict
                                            </span>
                                        </div>
                                        <div class="text-sm text-slate-600">
                                            {{ formatRange(event) }}
                                            <span v-if="event.location">· {{ event.location }}</span>
                                            <span v-if="reminderLabel(event)" class="ml-2 text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full">
                                                {{ reminderLabel(event) }}
                                            </span>
                                        </div>
                                        <div v-if="event.description" class="text-sm text-slate-500">
                                            {{ event.description }}
                                        </div>
                                        <div v-if="event.attendees?.length" class="flex flex-wrap gap-2">
                                            <span
                                                v-for="att in event.attendees"
                                                :key="att.id"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full border border-slate-200 text-slate-700"
                                            >
                                                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: att.user?.avatar_color || '#14b8a6' }"></span>
                                                {{ att.user?.name || 'Member' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex gap-3 text-sm">
                                        <button
                                            type="button"
                                            class="text-indigo-600 hover:text-indigo-700"
                                            @click="startEdit(event)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="text-rose-600 hover:text-rose-700"
                                            @click="deleteEvent(event.id)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
