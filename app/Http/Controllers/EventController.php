<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $calendarFilter = collect($request->input('calendars', []))->filter()->map(fn ($id) => (int) $id)->all();
        $categoryFilter = $request->input('category');

        $calendars = Calendar::where('household_id', $user->household_id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'is_default']);

        $events = Event::with([
            'calendar:id,name,color',
            'creator:id,name',
            'attendees.user:id,name,avatar_color',
        ])
            ->whereHas('calendar', fn ($q) => $q->where('household_id', $user->household_id))
            ->when(!empty($calendarFilter), fn ($q) => $q->whereIn('calendar_id', $calendarFilter))
            ->when($categoryFilter, fn ($q) => $q->where('category', $categoryFilter))
            ->orderBy('start_at')
            ->limit(200)
            ->get([
                'id',
                'calendar_id',
                'creator_id',
                'title',
                'description',
                'location',
                'start_at',
                'end_at',
                'is_all_day',
                'visibility',
                'category',
            ]);

        $members = User::where('household_id', $user->household_id)
            ->orderBy('name')
            ->get(['id', 'name', 'avatar_color']);

        return Inertia::render('Calendar/Index', [
            'calendars' => $calendars,
            'events' => $events,
            'members' => $members,
            'filters' => [
                'calendars' => $calendarFilter,
                'category' => $categoryFilter,
            ],
            'defaults' => [
                'calendarId' => optional($calendars->firstWhere('is_default', true))->id ?? optional($calendars->first())->id,
                'start' => now()->format('Y-m-d\TH:i'),
                'end' => now()->addHour()->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'calendar_id' => ['required', 'exists:calendars,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'is_all_day' => ['sometimes', 'boolean'],
            'visibility' => ['required', 'in:household,attendees,private'],
            'category' => ['nullable', 'string', 'max:100'],
            'attendees' => ['array'],
            'attendees.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'attendees.*.reminder_offset_minutes' => ['nullable', 'integer'],
            'reminder_offset_minutes' => ['nullable', 'integer'],
        ]);

        Calendar::where('household_id', $user->household_id)
            ->where('id', $data['calendar_id'])
            ->firstOrFail();

        $allowedAttendees = User::where('household_id', $user->household_id)
            ->whereIn('id', collect($data['attendees'] ?? [])->pluck('user_id'))
            ->pluck('id')
            ->unique()
            ->values();

        $event = Event::create([
            ...$data,
            'creator_id' => $user->id,
        ]);

        $attendeeIds = $this->syncAttendees($event, $allowedAttendees, $data['reminder_offset_minutes'] ?? null, $data['attendees'] ?? []);
        $this->scheduleNotifications($event, $attendeeIds, $data['reminder_offset_minutes'] ?? null, $data['attendees'] ?? []);

        return redirect()->route('calendar.index')->with('success', 'Event created.');
    }

    public function update(Request $request, Event $event)
    {
        $user = $request->user();

        if ($event->calendar->household_id !== $user->household_id) {
            abort(403);
        }

        $data = $request->validate([
            'calendar_id' => ['required', 'exists:calendars,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'is_all_day' => ['sometimes', 'boolean'],
            'visibility' => ['required', 'in:household,attendees,private'],
            'category' => ['nullable', 'string', 'max:100'],
            'attendees' => ['array'],
            'attendees.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'attendees.*.reminder_offset_minutes' => ['nullable', 'integer'],
            'reminder_offset_minutes' => ['nullable', 'integer'],
        ]);

        Calendar::where('household_id', $user->household_id)
            ->where('id', $data['calendar_id'])
            ->firstOrFail();

        $allowedAttendees = User::where('household_id', $user->household_id)
            ->whereIn('id', collect($data['attendees'] ?? [])->pluck('user_id'))
            ->pluck('id')
            ->unique()
            ->values();

        $event->update($data);

        $attendeeIds = $this->syncAttendees($event, $allowedAttendees, $data['reminder_offset_minutes'] ?? null, $data['attendees'] ?? []);
        $this->scheduleNotifications($event, $attendeeIds, $data['reminder_offset_minutes'] ?? null, $data['attendees'] ?? []);

        return redirect()->route('calendar.index')->with('success', 'Event updated.');
    }

    public function destroy(Event $event)
    {
        $user = Auth::user();
        if ($event->calendar->household_id !== $user->household_id) {
            abort(403);
        }

        $event->notifications()->delete();
        $event->attendees()->delete();
        $event->delete();

        return redirect()->route('calendar.index')->with('success', 'Event deleted.');
    }

    private function syncAttendees(Event $event, $attendeeIds, ?int $reminderOffset, array $attendeePayload)
    {
        $event->attendees()->delete();

        $uniqueIds = collect($attendeeIds ?? [])
            ->push($event->creator_id) // ensure creator is tracked
            ->unique()
            ->values();

        $offsetLookup = collect($attendeePayload ?? [])
            ->keyBy('user_id')
            ->map(fn ($row) => $row['reminder_offset_minutes'] ?? $reminderOffset);

        $payload = $uniqueIds->map(function ($id) use ($event, $reminderOffset, $offsetLookup) {
            $offset = $offsetLookup->get($id, $reminderOffset);
            return [
                'user_id' => $id,
                'status' => $id === $event->creator_id ? 'accepted' : 'invited',
                'reminder_offset_minutes' => $offset,
            ];
        });

        if ($payload->isNotEmpty()) {
            $event->attendees()->createMany($payload->all());
        }

        return $uniqueIds;
    }

    private function scheduleNotifications(Event $event, $attendeeIds, ?int $reminderOffset, array $attendeePayload): void
    {
        $event->notifications()->delete();

        $lookup = collect($attendeePayload ?? [])->keyBy('user_id');
        $notifications = collect($attendeeIds ?? [])->map(function ($userId) use ($event, $reminderOffset, $lookup) {
            $offset = $lookup->get($userId)['reminder_offset_minutes'] ?? $reminderOffset ?? 60;
            $sendAt = Carbon::parse($event->start_at)->subMinutes($offset);

            return [
                'user_id' => $userId,
                'event_id' => $event->id,
                'type' => 'reminder',
                'send_at' => $sendAt,
                'status' => 'pending',
                'payload' => [
                    'title' => $event->title,
                    'start_at' => $event->start_at,
                    'offset_minutes' => $offset,
                ],
            ];
        });

        if ($notifications->isNotEmpty()) {
            $event->notifications()->createMany($notifications->all());
        }
    }
}
