# TraceReq repository guide

## Purpose and source of truth

TraceReq is currently a pair of independent scaffolds:

- `backend/`: Laravel JSON API.
- `frontend/`: Vue 3 single-page application.
- `docs/`: product, architecture, MVP, and walkthrough decisions.
- `TraceReq_diccionario_datos_v1.md`: a broader, earlier data-model proposal.

Read `docs/PRODUCT.md`, `docs/ARCHITECTURE.md`, and `docs/MVP.md` before changing feature code. For the immediate implementation, those documents override the broader scope in the data dictionary. Installed manifests and lockfiles are the source of truth for dependencies.

## Current implementation status

The repository is still framework scaffold code. It does not yet implement request creation, listing, or detail views. Do not describe planned behavior as implemented.

The first vertical slice is limited to:

- creating an internal request;
- listing internal requests;
- viewing one internal request.

Do not add authentication, authorization, approvals, attachments, notifications, advanced audit trails, or complete multi-tenancy as part of that slice. An unauthenticated local demo is not production-ready; say so explicitly.

## Architectural direction

Use three concerns pragmatically:

- **Domain** names business concepts and holds business rules only when they exist.
- **Application** coordinates the `CreateRequest`, `ListRequests`, and `ViewRequest` use cases.
- **Infrastructure** contains Laravel HTTP routing, validation, JSON resources, Eloquent persistence, migrations, and the Vue API adapter.

Keep Laravel conventions visible. Simple CRUD may use Eloquent directly from a small application action. Do not introduce repository interfaces, a generic service layer, DTO hierarchies, event buses, or a Pinia store without a concrete need. Controllers should translate HTTP input/output, not contain the use case. Vue views should compose feature components and call a small request API module.

See `docs/ARCHITECTURE.md` for the proposed paths and dependency direction.

## Working rules

- Inspect both applications and `git status` before editing.
- Preserve unrelated user changes and ignored local environment files.
- Never commit `.env`, `vendor/`, `node_modules/`, build output, logs, or secrets.
- Keep the API under `/api`; the Vue SPA remains an independently served application.
- Return JSON for API success and validation/error responses.
- Represent decimal money as strings at the API boundary; do not use floating-point arithmetic for money.
- Keep migrations reversible and tests deterministic.
- Add focused backend feature tests for endpoints and frontend tests only after a frontend test runner is deliberately selected.
- Update the docs when scope, API contracts, or architectural decisions change.

## Local commands

Backend, from `backend/`:

```sh
composer install
php artisan migrate
php artisan serve
php artisan test
vendor/bin/pint --test
```

Frontend, from `frontend/`:

```sh
npm install
npm run dev
npm run build
npm run lint
```

The frontend declares Node `^22.18.0 || >=24.12.0`; use a compatible runtime. At the time this guide was written, the local Node 22.14.0 installation did not meet that constraint. `npm run lint` is configured to apply fixes, so inspect its diff.

## Verification before handoff

Run checks proportional to the change. For feature work, the expected minimum is backend tests plus frontend build and lint. Report commands that were not run and why. Confirm the final diff contains only intended files and distinguish current behavior from planned behavior in the handoff.
