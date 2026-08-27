# TraceReq repository guide

## Purpose and source of truth

TraceReq is a pair of independently served applications:

- `backend/`: Laravel JSON API.
- `frontend/`: Vue 3 single-page application.
- `docs/`: product, architecture, MVP, and walkthrough decisions.
- `TraceReq_diccionario_datos_v1.md`: a broader, earlier data-model proposal.

Read `docs/PRODUCT.md`, `docs/ARCHITECTURE.md`, and `docs/MVP.md` before changing feature code. For the immediate implementation, those documents override the broader scope in the data dictionary. Installed manifests and lockfiles are the source of truth for dependencies.

## Current implementation status

The Laravel backend implements the create, list, and detail request API. The Vue frontend implements the matching list, create, detail, and client-side not-found routes, together with loading, empty, validation, not-found, and general error states. Distinguish this implemented vertical slice from deferred product capabilities.

The first vertical slice is limited to:

- creating an internal request;
- listing internal requests;
- viewing one internal request.

Do not add authentication, authorization, approvals, attachments, notifications, advanced audit trails, or complete multi-tenancy as part of that slice. An unauthenticated local demo is not production-ready; say so explicitly.

Its request record contains only a server-generated ULID `id`, `title`, `description`, decimal-string `requested_amount`, `currency_code`, `created_at`, and `updated_at`. A human-readable request reference is deferred until organization-aware sequencing exists.

## Architectural direction

Use three concerns pragmatically:

- **Domain** names business concepts and holds business rules only when they exist.
- **Application** coordinates the `CreateRequest`, `ListRequests`, and `ViewRequest` use cases; validated create input crosses this boundary in a `CreateRequestData` DTO.
- **Infrastructure** contains Laravel HTTP routing, validation, JSON resources, Eloquent persistence, migrations, and the Vue API adapter.

Add a `Money` value object because fixed-precision monetary handling is a genuine reusable domain concern. Keep Laravel conventions visible: application actions deliberately use Eloquent directly for this CRUD-sized slice. Defer repository contracts until persistence substitution, complex aggregate persistence, tenant-aware queries, or workflow complexity creates a concrete need. Do not introduce a generic service layer, DTO hierarchies, event buses, empty placeholder folders, or a Pinia store without a concrete need. Controllers should translate HTTP input/output, not contain the use case. Vue views should compose feature components and call a small request API module using native `fetch` and relative `/api` paths.

See `docs/ARCHITECTURE.md` for the proposed paths and dependency direction.

## Working rules

- Inspect both applications and `git status` before editing.
- Preserve unrelated user changes and ignored local environment files.
- Never commit `.env`, `vendor/`, `node_modules/`, build output, logs, or secrets.
- Keep the API under `/api`; the Vue SPA remains an independently served application.
- Use PostgreSQL for the local application and recorded demonstration. SQLite `:memory:` is permitted only for fast initial portable feature tests and does not validate PostgreSQL-specific behavior.
- Add PostgreSQL integration tests before relying on PostgreSQL-specific constraints, indexes, concurrency behavior, or SQL features.
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

Use Node 24 LTS as the canonical frontend development runtime, with Node 24.12.0 or newer in that LTS line. The compatible package engine declaration remains `^22.18.0 || >=24.12.0`, but the previous local Node 22.14.0 installation did not satisfy it. `npm run lint` is configured to apply fixes, so inspect its diff.

## Verification before handoff

Run checks proportional to the change. For feature work, the expected minimum is backend tests plus frontend build and lint. Report commands that were not run and why. Confirm the final diff contains only intended files and distinguish current behavior from planned behavior in the handoff.
