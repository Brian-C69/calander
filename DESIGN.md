# Design Overview
- **App**: Family Calendar (colorful, multi-user, shared schedules)
- **Stack**: Laravel + Inertia + Vue + Tailwind/shadcn; DB MySQL/Postgres; Auth via Breeze/Fortify
- **Location**: `C:\xampp\htdocs\calander`
- **Theme Tokens**: teal `#14b8a6`, mango `#f59e0b`, orchid `#c084fc`, indigo `#312e81`, frost `#e0f2fe`, slate `#0f172a`

## ERD (MVP)
- households: id, name, created_at/updated_at
- users: id, household_id, name, email, password, role (admin/member), avatar_color, email_verified_at, remember_token, timestamps
- calendars: id, household_id, name, color, visibility_scope (household/private), owner_id, is_default, timestamps
- calendar_members: id, calendar_id, user_id, role (owner/editor/viewer), timestamps
- events: id, calendar_id, creator_id, title, description, location, start_at, end_at, is_all_day, recurrence_rule (rrule string), recurrence_end, visibility (household/attendees/private), category, timestamps
- event_attendees: id, event_id, user_id, status (invited/accepted/declined/tentative), reminder_offset_minutes, timestamps
- notifications: id, user_id, event_id, type (reminder/change/cancel/digest), send_at, status (pending/sent), payload(json), timestamps
- comments (v1.1 optional): id, event_id, user_id, body, timestamps
- attachments (v1.1 optional): id, event_id, url, mime_type, timestamps

## Routes (Inertia)
- Auth: `/login`, `/register`, `/forgot-password`, `/reset-password`
- Dashboard `/`: week strip + today card + quick add + upcoming list
- Calendar `/calendar`: month/week/day/agenda views; filters (members/categories/calendars); conflict badges; color legend
- Event detail `/event/{id}`: details, attendees, reminders, history; edit/delete; recurrence scope (this vs future)
- Settings
  - `/settings/household`: members, invites, roles, default reminder
  - `/settings/calendars`: create/edit calendars, colors, visibility, default selection
  - `/settings/notifications`: reminder defaults, daily digest toggle
  - `/import`: ICS subscribe (one-way) and export per calendar (v1.1)

## UI Components (Vue)
- Layout: shell with nav, color legend, user menu
- CalendarViews: MonthView, WeekView, DayView, AgendaList (shared data source)
- EventModal: create/edit; fields for title/time/all-day/location/notes/attendees/category/visibility/reminders/recurrence
- FiltersBar: member chips, calendar selector, category pills, conflict badge indicator
- QuickAddBar: inline title + date/time + default Family calendar; add attendees
- MemberAvatar: colored pill/avatar with initials
- NotificationBell: in-app alerts + digest toggle shortcut
- EmptyStates: playful illustrations for no events/search empty

## Phased Tasks
### Phase 1 – Foundations
- [ ] Init Laravel + Inertia + Vue + Tailwind/shadcn; Breeze/Fortify auth
- [ ] Migrations/models/factories/seeders for households, users, calendars, calendar_members
- [ ] Seed default household (for dev) and Family calendar; assign avatar colors
- [ ] Layout shell + theme tokens

### Phase 2 – Events Core
- [ ] Migrations/models for events, event_attendees, notifications
- [ ] Event CRUD + attendee selection + visibility + category
- [ ] Simple recurrence (daily/weekly/monthly until date) and storage in recurrence_rule
- [ ] Reminder scheduling (store notification rows; backend hook stub)
- [ ] Calendar views (month/week/day/agenda) with filters and conflict badge
- [ ] Quick add flow

### Phase 3 – Notify/Collab
- [ ] In-app notifications feed; email reminders/change/cancel; daily digest toggle
- [ ] Optional comments/reactions component
- [ ] Edit recurrence scope (this vs future)

### Phase 4 – Polish/PWA
- [ ] PWA shell, offline read, queued writes for events
- [ ] ICS export per calendar; ICS subscribe (one-way)
- [ ] Attachments (optional) and reactions polish
