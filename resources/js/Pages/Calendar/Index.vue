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
const editingId = ref(null);

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
});

const filteredEvents = computed(() =>
    props.events.filter((event) => {
        const calendarOk = selectedCalendars.value.includes(event.calendar_id);
        const categoryOk = category.value ? event.category === category.value : true;
        return calendarOk && categoryOk;
    }),
);

const eventsByDay = computed(() => {
    const grouped = {};
    filteredEvents.value.forEach((event) => {
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

const submit = () => {
    const payload = {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'description', 'location', 'is_all_day', 'category', 'attendees');
            showForm.value = false;
            editingId.value = null;
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
    form.attendees = (event.attendees || []).map((a) => a.user_id || a.id);
};

const cancelEdit = () => {
    editingId.value = null;
    showForm.value = false;
    form.reset('title', 'description', 'location', 'is_all_day', 'category', 'attendees');
    form.calendar_id = props.defaults.calendarId;
    form.start_at = props.defaults.start;
    form.end_at = props.defaults.end;
    form.visibility = 'household';
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
                                <label
                                    v-for="member in members"
                                    :key="member.id"
                                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        :value="member.id"
                                        v-model="form.attendees"
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: member.avatar_color || '#14b8a6' }"></span>
                                    <span class="text-slate-700">{{ member.name }}</span>
                                </label>
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
                        <h3 class="text-lg font-semibold text-slate-800">Upcoming</h3>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="cal in calendars" :key="cal.id" class="flex items-center gap-2 text-sm">
                                <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: cal.color || '#14b8a6' }"></span>
                                <span class="text-slate-700">{{ cal.name }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="eventsByDay.length === 0" class="text-slate-500 text-sm">
                        No events yet. Add one to get started.
                    </div>

                    <div v-for="[day, list] in eventsByDay" :key="day" class="mb-6 last:mb-0">
                        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ day }}</div>
                        <div class="space-y-3">
                            <div
                                v-for="event in list"
                                :key="event.id"
                                class="border border-slate-200 rounded-lg p-4 flex items-start justify-between"
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
                                        {{ event.start_at }} → {{ event.end_at }}
                                        <span v-if="event.location">· {{ event.location }}</span>
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
    </AuthenticatedLayout>
</template>
