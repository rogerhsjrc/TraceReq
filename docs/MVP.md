# Immediate MVP plan

## Scope statement

The immediate MVP is one complete, demonstrable vertical slice for internal requests: create, list, and view. It is not implemented as of this document.

## User stories

### Create a request

As a team member in a local demonstration, I can enter a title, description, requested amount, and currency so that the request is persisted.

Acceptance criteria:

- required fields are validated by Laravel;
- amount must be a positive decimal and must not be handled as a floating-point value;
- currency is normalized/validated as a three-letter uppercase code;
- success returns `201` and the saved representation;
- the SPA shows validation and transport errors without losing the entered values.

### List requests

As a team member, I can see all saved demo requests so that I can choose one to inspect.

Acceptance criteria:

- `GET /api/requests` returns a JSON collection;
- results have deterministic newest-first ordering;
- the SPA shows loading, empty, error, and populated states;
- each row links to its detail route.

### View a request

As a team member, I can open a saved request so that I can read all of its recorded fields.

Acceptance criteria:

- `GET /api/requests/{id}` returns the same field representation used elsewhere;
- an unknown identifier returns `404`;
- the SPA supports direct navigation and refresh on the detail URL;
- loading, error, and not-found states are visible.

## Delivery slices

1. **Persistence and contract**: add one reversible migration, one Eloquent model, request validation, one JSON resource, and backend feature tests for the three endpoints.
2. **Application flow**: add the three small application actions and thin API controller methods/routes.
3. **SPA flow**: add the fetch adapter, three routes/views, form behavior, and basic loading/error/empty states.
4. **Demo readiness**: seed or create a small deterministic dataset, run checks, and rehearse `docs/VIDEO_DEMO.md`.

Each slice should leave the relevant checks green. Do not start deferred capabilities to make the demonstration appear more complete.

## Definition of done

- The three API endpoints meet their acceptance criteria.
- The three SPA routes work against a fresh migrated local database.
- Backend endpoint tests pass.
- Backend formatting check passes.
- Frontend lint and production build pass on a supported Node version.
- Setup and API base URL requirements are documented.
- The five-minute walkthrough can be performed from a known starting state.
- No UI or documentation implies authentication, approval, audit, attachment, notification, or tenant isolation.

## Not in this MVP

Authentication, approvals, attachments, notifications, advanced auditing, complete multi-tenancy, editing, deletion, searching, filtering, pagination, and request workflow state changes are excluded.

The existing `/api/user` route and installed Sanctum package are scaffold artifacts, not proof of a complete authentication feature. The example Pinia store is likewise not product state management.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| The broader data dictionary expands the slice | Treat this file and `PRODUCT.md` as the immediate scope authority |
| SQLite and the dictionary’s PostgreSQL design diverge | Keep the first schema portable; make database selection a later explicit decision |
| Local Node is below the declared engine | Upgrade to Node 22.18+ or 24.12+ before frontend verification |
| Separate dev servers cannot communicate | Configure an environment-based API URL and explicit local CORS/proxy behavior |
| Demo data varies between runs | Document a repeatable seed/reset procedure when feature code is implemented |

## Decisions required before implementation

- Choose the server-generated identifier: Laravel’s conventional integer ID or the data dictionary’s ULID direction.
- Decide whether the first record needs a human-readable reference.
- Confirm the canonical local ports/origins and whether Vite proxies `/api` or Laravel enables local CORS.
- Confirm whether the four proposed fields are sufficient for the walkthrough or whether one additional field is essential.

These decisions should be made before migrations and API response shapes are committed. They do not justify adding the deferred systems.
