# Five-minute technical walkthrough

## Status and goal

This is the planned script for the request vertical slice after it is implemented. The current repository is scaffold-only, so the feature portion is not runnable yet.

The walkthrough should demonstrate one end-to-end idea clearly: Vue collects an internal request, Laravel validates and persists it, and Vue lists and displays the returned resource.

## Preparation

- Use a Node version satisfying `^22.18.0 || >=24.12.0`.
- Install backend and frontend dependencies.
- Start from a fresh, migrated demo database with a known small dataset.
- Run backend tests and the frontend production build before recording.
- Start Laravel and Vite on the documented local origins.
- Open the request list and keep terminals at readable font sizes.
- Do not show `.env`, tokens, credentials, personal data, or unrelated local changes.

## Timeline

### 0:00–0:35 — Frame the slice

State that `backend/` is an independent Laravel JSON API and `frontend/` is an independent Vue 3 SPA. Set expectations: this demo covers only create, list, and view. Authentication, approvals, attachments, notifications, advanced auditing, and full multi-tenancy are intentionally deferred.

### 0:35–1:20 — Show the architecture in code

Open the three request routes and one thin controller method. Follow a create call into its application action, then point to the Eloquent model/migration and JSON resource. Explain that Domain, Application, and Infrastructure are responsibilities: simple validation stays simple, and there is no repository abstraction without a real substitution need.

### 1:20–2:20 — Create through Vue

Open `/requests/new`. Briefly show the Vue form and request API adapter, then submit a valid request. Point out the `POST /api/requests` call, `201` response, decimal-string amount, server-generated ID, and timestamps.

Spend a few seconds submitting one invalid value so the `422` field error is visible, then return to the successful flow. Do not imply that a user is authenticated.

### 2:20–3:10 — List requests

Navigate to `/requests`. Show the saved request in deterministic newest-first order and mention loading/empty/error states without dwelling on styling. In the network panel, identify `GET /api/requests` and the consistent resource shape.

### 3:10–3:50 — View one request

Open the request detail route, refresh it directly, and show `GET /api/requests/{id}`. Briefly demonstrate a not-found URL if timing permits. Emphasize that the backend owns persistence and the SPA owns presentation.

### 3:50–4:35 — Show verification

Run or show the completed backend feature tests covering create, validation, list order, detail, and `404`. Show the successful frontend production build. Avoid spending the demo on framework-generated example tests.

### 4:35–5:00 — Close with boundaries

Recap the full path: Vue form → fetch adapter → Laravel route/controller → application action → Eloquent/database → JSON resource → Vue list/detail. Close by naming the deferred capabilities and noting that authentication/tenant design must precede production exposure.

## Presenter notes

- Prefer one successful narrative over a tour of every file.
- Keep the browser network panel filtered to `requests`.
- Describe only behavior visible in the checked-out code.
- If a check is not green, state that directly; do not substitute a cached build or prerecorded response.
- Keep the data dictionary off the main path unless asked; explain that it is a broader future proposal.

## Fallback if feature implementation is not ready

Do not fake the product flow. Give a repository/architecture walkthrough using these docs, show the current scaffold honestly, and identify the next implementation slice from `docs/MVP.md`.
