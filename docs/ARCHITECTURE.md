# TraceReq architecture

## Status

The recorded create/list/view MVP was successfully presented. This document records the current architecture, including the subsequent draft/submission feature. Development now explores Laravel and Vue through bounded, tested features. The application remains an unauthenticated local learning demo, not production-ready.

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
- `SubmitRequest`

The Domain concern includes the string-backed `RequestStatus` enum (`Draft`, `Submitted`). `CreateRequest` assigns Draft on the server; status is not part of `CreateRequestData`. `SubmitRequest` updates the existing record and returns an already-submitted record unchanged.

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

The frontend's infrastructure adapters are a centralized transport client at `frontend/src/api/http.js` and the request endpoint module at `frontend/src/features/requests/api/requests.js`. They use native browser `fetch` with relative `/api` paths; Axios or another HTTP dependency is unnecessary for these four calls.

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

## Current API contract

All endpoints are unauthenticated for the local first demo only:

| Method | Path | Use case | Expected result |
|---|---|---|---|
| `POST` | `/api/requests` | Create | `201` with the saved draft |
| `GET` | `/api/requests` | List | `200` with a newest-first collection |
| `GET` | `/api/requests/{id}` | View | `200` with one request; `404` if absent |
| `POST` | `/api/requests/{id}/submit` | Submit | `200` with the submitted request; JSON `404` if absent |

Validation failures should use Laravel’s standard JSON `422` structure. Resources should keep a consistent request representation across create, list, detail, and submission. The list may return an unpaginated collection for the tiny demo dataset; pagination should be added only when the product needs it.

Every response record contains exactly `id`, `title`, `description`, `requested_amount`, `currency_code`, `created_at`, `updated_at`, and `status`. The server generates `id` as a Laravel-supported ULID. `requested_amount` is always a decimal string; neither calculations nor serialization may use floating-point values. There is no human-readable request reference in this increment.

Creation always returns a complete draft, regardless of client-supplied status. The additive reversible migration adds a string status column with a `draft` default, including for existing rows. Eloquent casts it to `RequestStatus`; the enum does not create a database allowed-values constraint. Submission preserves identity and business fields. Sequential retries preserve timestamps; the current read/check/save operation does not guarantee concurrent submissions avoid duplicate writes.

The Vue detail view owns `submitting`, `submitError`, and a submission version guard. It disables Submit while pending, updates from the returned resource, clears errors on retry, and invalidates stale submission results on route changes/unmount. The native-fetch request API module owns HTTP calls. No shared store is required.

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

Unit tests cover the `Money` value object and any other standalone domain rule. Feature tests use `RefreshDatabase` and cover creation, validation, persistence, deterministic newest-first ordering, successful detail retrieval, consistent serialization, server-owned draft creation, submission preserving business fields, sequential retries preserving timestamps, and JSON `404` for unknown submission IDs.

SQLite `:memory:` may provide fast feedback for these initial tests because the first request migration must remain portable. Passing those tests does not validate PostgreSQL-specific constraints, indexes, SQL features, transaction semantics, or concurrency behavior. PostgreSQL integration tests become mandatory before the application relies on any such behavior. The application demonstration itself always runs against PostgreSQL.

The frontend currently has no test runner or Vue component testing library. Verify it with lint, a production build, and a manual browser pass until frontend testing is deliberately selected; do not add a testing framework incidentally.

## Deferred evolution

The four request endpoints are unauthenticated only for controlled local learning and demonstration and must not be exposed publicly in that form. The generated `/api/user` scaffold route was removed. Sanctum remains installed for a future authentication increment, but no product authentication route or session flow is implemented.

Authentication, authorization, policies, roles, tenant isolation, and tenant context will later change endpoint access and likely add requester/organization ownership to records. Approvals will introduce workflow rules that belong in Domain and Application rather than generic update endpoints. Attachments, comments, notifications, queues, advanced auditing, human-readable sequencing, editing, deletion, searching, filtering, pagination, and production deployment architecture are separate increments with their own decisions and tests.
