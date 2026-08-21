# TraceReq architecture

## Status

This document records the target architecture for the first request vertical slice. The described feature folders and endpoints are proposed; they do not exist yet.

## System context

```text
Browser
  -> Vue 3 SPA (frontend/, independently served)
       -> JSON over HTTP at /api
            -> Laravel API (backend/, independently served)
                 -> relational database
```

The backend owns validation, persistence, ordering, identifiers, and response shape. The frontend owns interaction and presentation. The SPA must not depend on Blade templates, and Laravel must not render the product UI.

## Current repository baseline

The backend is Laravel 13 with the default `User` model and framework migrations, a Sanctum-protected `/api/user` scaffold route, a Blade welcome page, Laravel-side Vite/Tailwind setup, and example Pest/PHPUnit tests. The frontend is a Vue 3/Vite scaffold with an empty router and the example Pinia counter. There is no request model, migration, controller, application action, API client, or request screen.

The default local and test database is SQLite. PostgreSQL is configured as an available Laravel connection but is not the repository default. Database-specific features must not be assumed in the first slice.

## Modular concerns

The architecture uses Domain, Application, and Infrastructure as responsibilities, not as a mandate to wrap every framework class.

### Domain

The Domain concern defines the language and genuine business invariants of an internal request. In the first CRUD slice, most rules are simple required fields and ranges and can remain Laravel validation rules. Create `backend/app/Domain/Requests/` only when a rule deserves a reusable PHP type, enum, or policy.

Domain code, when introduced, must not import HTTP requests, controllers, or Vue concepts.

### Application

`backend/app/Application/Requests/` should contain one small invokable action per use case:

- `CreateRequest`
- `ListRequests`
- `ViewRequest`

Actions coordinate the operation and make the walkthrough explicit. For this simple slice they may query an Eloquent model directly. Do not add repository interfaces merely to hide Eloquent, and do not create both “service” and “action” layers for the same behavior.

### Infrastructure and delivery

Laravel framework adapters remain in conventional locations:

- `backend/routes/api.php`: endpoint mapping;
- `backend/app/Http/Controllers/Api/`: thin HTTP controllers;
- `backend/app/Http/Requests/`: input validation;
- `backend/app/Http/Resources/`: stable JSON serialization;
- `backend/app/Models/`: Eloquent persistence models;
- `backend/database/migrations/`: schema;
- `backend/tests/Feature/`: endpoint behavior.

The frontend’s infrastructure adapter is a small `frontend/src/features/requests/api/requests.js` module using the browser `fetch` API. No HTTP client package is currently installed, so one is unnecessary for three calls.

## Proposed frontend structure

```text
frontend/src/
  features/requests/
    api/requests.js
    components/RequestForm.vue
    views/RequestCreateView.vue
    views/RequestListView.vue
    views/RequestDetailView.vue
  router/index.js
  App.vue
  main.js
```

Use route-level views for `/requests`, `/requests/new`, and `/requests/:id`. Keep loading, error, and response state local to each view. Pinia is installed but is not needed for short-lived server state in this slice; introduce a store only when state must be shared across routes or cached deliberately.

## Proposed API contract

All endpoints are unauthenticated for the local first demo only:

| Method | Path | Use case | Expected result |
|---|---|---|---|
| `POST` | `/api/requests` | Create | `201` with the saved request |
| `GET` | `/api/requests` | List | `200` with a newest-first collection |
| `GET` | `/api/requests/{id}` | View | `200` with one request; `404` if absent |

Validation failures should use Laravel’s standard JSON `422` structure. Resources should keep a consistent request representation across create, list, and detail. The list may return an unpaginated collection for the tiny demo dataset; pagination should be added only when the product needs it.

## Dependency direction

```text
Vue views -> frontend API adapter -> HTTP
HTTP route/controller -> application action -> Eloquent model/database
                         -> domain rule/type (only when needed)
```

Outer adapters may depend inward on application/domain concepts. Domain code must not depend outward on HTTP or UI. Eloquent is intentionally allowed in the application action for this CRUD-sized slice; if persistence alternatives or complex querying become real requirements, that decision can be revisited.

## Data and operational decisions

- Use a fixed-precision database column for money and serialize the value as a string.
- Validate currency as a three-letter uppercase code; a currency catalog is deferred.
- Let the server generate identifiers and timestamps.
- Order lists deterministically by creation time and identifier descending.
- Keep CORS/base URL configuration environment-driven when connecting the two dev servers.
- Use SQLite for the first local demo and automated backend tests unless a separate database decision is made.
- Do not use queues, notifications, Sanctum, or the Laravel frontend asset pipeline in the first request slice.

## Testing strategy

Backend feature tests should cover successful creation, validation errors, newest-first listing, successful detail retrieval, and `404`. A small unit test is justified only for a real standalone domain rule. The frontend currently has no test runner; do not claim component coverage or add a testing stack incidentally. Verify it with lint and a production build until frontend testing is separately selected.

## Deferred evolution

Authentication and a tenant context will later change endpoint access and likely add requester/organization ownership to records. Approvals will introduce workflow rules that belong in Domain and Application rather than generic update endpoints. Attachments, notifications, auditing, and PostgreSQL-specific constraints should be separate increments with their own decisions and tests.
