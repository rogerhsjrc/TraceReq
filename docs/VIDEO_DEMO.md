# Five-minute technical walkthrough

## Status and goal

This is the script for the implemented request vertical slice. The backend API and Vue request views are complete, so the end-to-end feature walkthrough is runnable with both development servers and a migrated PostgreSQL database.

The walkthrough should demonstrate one end-to-end idea clearly: Vue collects an internal request, Laravel validates and persists it, and Vue lists and displays the returned resource.

## Preparation

- Use Node 24 LTS, version 24.12.0 or newer in that line. The previous local Node 22.14.0 runtime was below the package's declared engine requirement.
- Install backend and frontend dependencies.
- Use a dedicated, migrated PostgreSQL demo database with a known small dataset.
- Run backend tests and the frontend production build before recording.
- Start Laravel at `http://127.0.0.1:8000` and Vite with its development `/api` proxy targeting Laravel.
- Open the request list and keep terminals at readable font sizes.
- Do not show `.env`, tokens, credentials, personal data, or unrelated local changes.

Start the API from `backend/`:

```sh
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Start the SPA from `frontend/` in a second terminal:

```sh
npm install
npm run dev
```

Open the URL printed by Vite (normally `http://localhost:5173/requests`). The SPA calls relative `/api` paths, and Vite proxies them to Laravel during development.

## Timeline

### 0:00–0:35 — Frame the slice

State that `backend/` is an independent Laravel JSON API and `frontend/` is an independent Vue 3 SPA. Set expectations: this demo covers only create, list, and view. Authentication, approvals, attachments, notifications, advanced auditing, and full multi-tenancy are intentionally deferred.

### 0:35–1:20 — Show the architecture in code

Open the three request routes and one thin controller method. Follow the create path: Form Request → thin controller → `CreateRequest` → `CreateRequestData` → `Money` → Eloquent model → PostgreSQL → JSON Resource. Explain that `Money` represents a real domain invariant, while direct Eloquent use is a deliberate pragmatic choice for this CRUD-sized slice. Repository contracts remain appropriate later for persistence substitution, complex aggregates, tenant-aware queries, or approval workflows.

### 1:20–2:20 — Create through Vue

Open `/requests/new`. Briefly show the Vue form and native `fetch` adapter calling the relative `/api/requests` path through the Vite proxy, then submit a valid request. Point out the `201` response, decimal-string amount, server-generated ULID, and timestamps. Note that no human-readable reference is part of this slice.

Spend a few seconds submitting one invalid value so the `422` field error is visible, then return to the successful flow. Do not imply that a user is authenticated.

### 2:20–3:10 — List requests

Navigate to `/requests`. Show the saved request in deterministic newest-first order and mention loading/empty/error states without dwelling on styling. In the network panel, identify `GET /api/requests` and the consistent resource shape.

### 3:10–3:50 — View one request

Open the request detail route, refresh it directly, and show `GET /api/requests/{id}`. Briefly demonstrate a not-found URL if timing permits. Emphasize that the backend owns persistence and the SPA owns presentation.

### 3:50–4:35 — Show verification

Run or show the `Money` unit tests and completed `RefreshDatabase` feature tests covering create, validation, persistence, list order, consistent serialization, detail, and `404`. Explain briefly that fast SQLite `:memory:` tests do not prove PostgreSQL-specific behavior; the live demonstration is the PostgreSQL path, and database-specific features will require PostgreSQL integration tests. Show the successful frontend lint and production build. Avoid spending the demo on framework-generated example tests.

### 4:35–5:00 — Close with boundaries

Recap the full path: Vue → native fetch → Vite `/api` proxy → Laravel route → Form Request → thin controller → `CreateRequest` → `CreateRequestData` → `Money` → Eloquent → PostgreSQL → JSON Resource → Vue. Close by naming the deferred capabilities and stating that the unauthenticated endpoints are controlled-local-demo only and must not be exposed publicly.

## Presenter notes

- Prefer one successful narrative over a tour of every file.
- Keep the browser network panel filtered to `requests`.
- Describe only behavior visible in the checked-out code.
- If a check is not green, state that directly; do not substitute a cached build or prerecorded response.
- Keep the data dictionary off the main path unless asked; explain that it is a broader future proposal.
- Describe the Vite proxy as local-development plumbing, not production deployment architecture.

## Fallback if feature implementation is not ready

Do not fake the product flow. Give a repository/architecture walkthrough using these docs, show the current scaffold honestly, and identify the next implementation slice from `docs/MVP.md`.
