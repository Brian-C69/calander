# Agent Guidelines
- **Purpose**: Family calendar app (multi-user, colorful, shared schedules).
- **Stack**: Laravel + Inertia + Vue + Tailwind/shadcn; DB MySQL/Postgres; auth via Breeze/Fortify.
- **Paths**: Repo root = `C:\xampp\htdocs\calander`. Environment: XAMPP PHP, Node for frontend build.
- **Safety**: No destructive commands (avoid `git reset --hard`). Keep migrations idempotent; no secrets committed.
- **Style**: Concise, systems-first responses; default address: Bernard. Tone warm but low-noise.
- **Config**: Use `.env` for secrets; do not commit keys. Use migrations/seeders/factories for data.
- **Testing**: Prefer `php artisan test` for backend; `npm run build` for frontend sanity.
- **Color Tokens**: teal `#14b8a6`, mango `#f59e0b`, orchid `#c084fc`, indigo `#312e81`, frost `#e0f2fe`, slate `#0f172a`.
- **Defaults**: Seed a Family calendar on household creation; default reminder 1h.
