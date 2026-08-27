# TraceReq architecture

## Status

This document records the implemented architecture for the first request vertical slice. The backend endpoints and matching frontend feature are complete for the controlled local demonstration.

## System context

```text
Browser
  -> Vue 3 SPA (frontend/, independently served)
       -> JSON over HTTP at /api
            -> Laravel API (backend/, independently served)
                 -> PostgreSQL
```

The backend owns validation, persistence, ordering, identifiers, and response shape. The frontend owns interaction and presentation. The SPA must not depend on Blade templates, and Laravel must not render the product UI.

## Current repository baseline

The backend is Laravel 13 and contains the request model, migration, application actions, API controller, Form Request, JSON resource, factory, routes, and focused Pest tests. The unrelated `/api/user` scaffold route and generated example tests were removed; Sanctum remains installed for a future increment. The Vue 3/Vite frontend implements the list, create, detail, and client-side not-found routes with a native-fetch API client and route-local server state.

Laravel's configuration fallback remains SQLite, while `.env.example` now documents blank PostgreSQL connection placeholders for local application setup without credentials or machine-specific secrets. PHPUnit selects SQLite `:memory:` for the portable feature tests, subject to the testing limits below.

## Modular concerns

The architecture uses Domain, Application, and Infrastructure as responsibilities, not as a mandate to wrap every framework class.

### Domain

The Domain concern defines the language and genuine reusable business invariants of an internal request. The first slice introduces a `Money` value object because fixed-precision monetary representation is a real domain concern. Simple required-field and input-shape rules remain Laravel validation rules. Do not add other Domain folders or placeholder classes without a rule that belongs in them.

Domain code, when introduced, must not import HTTP requests, controllers, or Vue concepts.

### Application

`backend/app/Application/Requests/` should contain one small invokable action per use case:

- `CreateRequest`
- `ListRequests`
- `ViewRequest`

Actions coordinate the operation and make the walkthrough explicit. For this simple slice they may query an Eloquent model directly. Do not add repository interfaces merely to hide Eloquent, and do not create both “service” and “action” layers for the same behavior.

`CreateRequestData` is the one create-input DTO. It explicitly carries validated `title`, `description`, `requested_amount`, and `currency_code` from delivery into the `CreateRequest` action; it is not the start of a DTO hierarchy. List and detail need only their corresponding small actions unless later requirements create a meaningful input structure.

### Infrastructure and delivery

Laravel framework adapters remain in conventional locations:

- `backend/routes/api.php`: endpoint mapping;
- `backend/app/Http/Controllers/Api/`: thin HTTP controllers;
- `backend/app/Http/Requests/`: input validation;
- `backend/app/Http/Resources/`: stable JSON serialization;
- `backend/app/Models/`: Eloquent persistence models;
- `backend/database/migrations/`: schema;
- `backend/tests/Feature/`: endpoint behavior.

The frontend's infrastructure adapters are a centralized transport client at `frontend/src/api/http.js` and the request endpoint module at `frontend/src/features/requests/api/requests.js`. They use native browser `fetch` with relative `/api` paths; Axios or another HTTP dependency is unnecessary for three calls.

## Implemented frontend structure

```text
frontend/src/
  api/http.js
  assets/main.css
  features/requests/
    api/requests.js
    components/RequestCard.vue
    components/RequestForm.vue
    components/RequestStatePanel.vue
    formatters.js
    types.js
    views/RequestCreateView.vue
    views/RequestListView.vue
    views/RequestDetailView.vue
  router/index.js
  views/NotFoundView.vue
  App.vue
  main.js
```

Route-level views implement `/requests`, `/requests/new`, and `/requests/:id`; `/` redirects to the list and a fallback route presents a useful client-side `404`. Loading, error, and response state remain local to each view. Pinia remains installed but is not wired into the application because short-lived server state does not need a shared store; introduce one only when state must be shared across routes or cached deliberately.

During local development, Vite proxies `/api` to `http://127.0.0.1:8000`. This keeps the independently served SPA and API convenient without adding unnecessary local CORS handling. The proxy is a development arrangement, not a production deployment architecture; a future deployment may use an environment-driven API base URL.

## Proposed API contract

All endpoints are unauthenticated for the local first demo only:

| Method | Path | Use case | Expected result |
|---|---|---|---|
| `POST` | `/api/requests` | Create | `201` with the saved request |
| `GET` | `/api/requests` | List | `200` with a newest-first collection |
| `GET` | `/api/requests/{id}` | View | `200` with one request; `404` if absent |

Validation failures should use Laravel’s standard JSON `422` structure. Resources should keep a consistent request representation across create, list, and detail. The list may return an unpaginated collection for the tiny demo dataset; pagination should be added only when the product needs it.

Every response record contains exactly `id`, `title`, `description`, `requested_amount`, `currency_code`, `created_at`, and `updated_at`. The server generates `id` as a Laravel-supported ULID. `requested_amount` is always a decimal string; neither calculations nor serialization may use floating-point values. There is no human-readable request reference in this slice.

## Dependency direction

```text
Vue -> fetch adapter -> relative /api path -> Vite development proxy
Route -> Form Request -> thin controller -> CreateRequest action
      -> CreateRequestData DTO -> Money value object
      -> Eloquent model -> PostgreSQL -> JSON Resource -> Vue
```

The list and detail routes follow the same delivery-to-action-to-resource shape through `ListRequests` and `ViewRequest`, without create-only input objects.

Outer adapters may depend inward on application/domain concepts. Domain code must not depend outward on HTTP or UI. Direct Eloquent use in the application actions is a deliberate pragmatic choice for this CRUD-sized persistence need, not a rejection of repository contracts. Introduce a repository interface and Eloquent implementation only when persistence substitution, complex aggregate persistence, tenant-aware queries, or approval workflows create a concrete boundary worth maintaining.

## Data and operational decisions

- Use PostgreSQL as the target relational database and for the local application and recorded demonstration.
- Use a fixed-precision database column and the `Money` value object for money; serialize the value as a string.
- Validate currency as a three-letter uppercase code; a currency catalog is deferred.
- Generate the primary `id` on the server as a Laravel-supported ULID and let the server generate timestamps.
- Defer `REQ-{year}-{sequence}` until organization-aware sequencing and its required transactions exist.
- Order lists deterministically by creation time and identifier descending.
- Proxy relative frontend `/api` calls through Vite to `http://127.0.0.1:8000` during local development; defer production base-URL and deployment design.
- Do not use queues, notifications, Sanctum, or the Laravel frontend asset pipeline in the first request slice.

## Testing strategy

Unit tests cover the `Money` value object and any other standalone domain rule. Feature tests use `RefreshDatabase` and cover creation, validation, persistence, deterministic newest-first ordering, successful detail retrieval, consistent serialization across endpoints, and `404`.

SQLite `:memory:` may provide fast feedback for these initial tests because the first request migration must remain portable. Passing those tests does not validate PostgreSQL-specific constraints, indexes, SQL features, transaction semantics, or concurrency behavior. PostgreSQL integration tests become mandatory before the application relies on any such behavior. The application demonstration itself always runs against PostgreSQL.

The frontend currently has no test runner or Vue component testing library. Verify it with lint, a production build, and a manual browser pass until frontend testing is deliberately selected; do not add a testing framework incidentally.

## Deferred evolution

The three request endpoints are unauthenticated only for a controlled local demonstration and must not be exposed publicly in that form. The generated `/api/user` scaffold route was removed, and the repository currently exposes only the three request endpoints documented above. Sanctum remains installed for a future authentication increment, but no login route, authenticated-user endpoint, session flow, or other product authentication route is currently implemented.

Authentication, authorization, policies, roles, tenant isolation, and tenant context will later change endpoint access and likely add requester/organization ownership to records. Approvals will introduce workflow rules that belong in Domain and Application rather than generic update endpoints. Attachments, comments, notifications, queues, advanced auditing, human-readable sequencing, editing, deletion, searching, filtering, pagination, and production deployment architecture are separate increments with their own decisions and tests.
