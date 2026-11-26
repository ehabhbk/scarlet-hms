# Scarlet HMS — Implementation Status

Last updated: 2026-11-25

## Backend (Laravel 12)
- Status: complete and seeded for the current scope.
- Migrations, models, factories, seeders, services, form requests, resources, controllers, policies, permissions, domain events/listeners are implemented.
- `php artisan migrate:fresh --seed --force` succeeds (SQLite locally; PostgreSQL-portable migrations).
- Feature tests pass: Auth, Patient, Appointment/Queue, Encounter, RBAC.
- 57 API routes under `/api/v1`; Sanctum bearer auth; custom RBAC; audit logging.
- Implemented in this phase: pharmacy, billing, insurance, laboratory, radiology, accounting (chart of accounts, journal entries, trial balance, income statement, balance sheet), HR (employees), inpatient (wards, beds, admissions), insurance contracts, lab devices, server-side CSV/PDF export, Reverb broadcasting config + queue events, expanded frontend pages.
- Not yet implemented: ERP/general ledger, device integration, patient portal, queue TV advanced features, production PostgreSQL verification, running Reverb in production.

## Frontend (React + TypeScript + Vite)
- Status: complete for the current scope and builds successfully.
- Arabic-first RTL/LTR, Scarlet Care branding, splash screen, auth, app shell, all planned pages:
  Login, Dashboard, Patients (+detail/form), Appointments, Queues, Encounters (+detail), Staff, Roles, Settings, Audit, Reports, Queue Display, NotFound.
- TanStack Query polling, Zustand auth/UI, React Hook Form + Zod, Tailwind v4.
- Verified in browser: login, dashboard, staff, roles, settings (save), audit, reports, display; language switching; no console errors.
- CSV/PDF export available client-side and server-side (`/reports/export`).
- New module pages (pharmacy, billing, insurance, laboratory, radiology) added.

## Local constraints
- PHP 8.2.12 (target 8.3+); no `pgsql` extension, so SQLite is used locally.
- Backend dev server: http://127.0.0.1:8000
- Frontend dev server: http://127.0.0.1:5173
- Demo password: `Scarlet@2026`

## Deep phase (latest)
- Lab test parameters, surgery, maternity, nursery, device messages added.
- Attendance, payroll, commissions endpoints added.

