# Scarlet HMS — Implementation Status

Last updated: 2026-11-25

## Backend (Laravel 12)
- Status: complete and seeded for the current scope.
- Migrations, models, factories, seeders, services, form requests, resources, controllers, policies, permissions, domain events/listeners are implemented.
- `php artisan migrate:fresh --seed --force` succeeds (SQLite locally; PostgreSQL-portable migrations).
- Feature tests pass: Auth, Patient, Appointment/Queue, Encounter, RBAC.
- 57 API routes under `/api/v1`; Sanctum bearer auth; custom RBAC; audit logging.
- Not yet implemented: real LIS/RIS/PACS integrations, pharmacy/ERP/finance/insurance modules, WebSockets, report export backend, production PostgreSQL verification.

## Frontend (React + TypeScript + Vite)
- Status: complete for the current scope and builds successfully.
- Arabic-first RTL/LTR, Scarlet Care branding, splash screen, auth, app shell, all planned pages:
  Login, Dashboard, Patients (+detail/form), Appointments, Queues, Encounters (+detail), Staff, Roles, Settings, Audit, Reports, Queue Display, NotFound.
- TanStack Query polling, Zustand auth/UI, React Hook Form + Zod, Tailwind v4.
- Verified in browser: login, dashboard, staff, roles, settings (save), audit, reports, display; language switching; no console errors.
- CSV export is client-side from live endpoints; server-side export not implemented.

## Local constraints
- PHP 8.2.12 (target 8.3+); no `pgsql` extension, so SQLite is used locally.
- Backend dev server: http://127.0.0.1:8000
- Frontend dev server: http://127.0.0.1:5173
- Demo password: `Scarlet@2026`
