# Family Calendar (Laravel + Inertia + Vue)
A colorful shared calendar for households: multi-user, per-member filters, conflict hints, reminders, and simple recurrence.

## Features (MVP)
- Household + member invites; default Family calendar seeded.
- Events with attendees, visibility (family/attendees/private), simple recurrence (daily/weekly/monthly until date), reminders.
- Views: month/week/day + agenda; filters by member/category; conflict badges.
- Notifications: in-app + email reminders; daily digest toggle.
- Colorful UI tokens: teal `#14b8a6`, mango `#f59e0b`, orchid `#c084fc`, indigo `#312e81`, frost `#e0f2fe`, slate `#0f172a`.

## Stack
- Laravel, Inertia, Vue, Tailwind/shadcn; MySQL/Postgres; Breeze/Fortify auth.
- Location: `C:\xampp\htdocs\calander`

## Setup
1) `composer install`
2) `cp .env.example .env` and set DB creds
3) `php artisan key:generate`
4) `php artisan migrate --seed` (seeds household + Family calendar)
5) `npm install && npm run dev` (or `npm run build`)
6) `php artisan serve` (or Apache vhost via XAMPP)

## Scripts
- `php artisan test` – backend tests
- `npm run lint` / `npm run build` – frontend checks/build

## Roadmap
- Phase 1: Auth, households, member invites, seed Family calendar.
- Phase 2: Event CRUD, attendees, recurrence, reminders, month/week/day/agenda views, filters.
- Phase 3: Notifications (change/cancel, daily digest), conflict badges, quick-add bar, PWA shell.
- Phase 4: ICS export/import (subscribe), offline read/queued writes, comments/reactions (optional).
