# TraceReq product brief

## Product intent

TraceReq is intended to give a team a small, clear place to record and find internal spending requests. The immediate demonstration proves the basic path from entering a request in a Vue SPA to persisting it through a Laravel JSON API and reading it back.

This is a technical demonstration, not a production release. The repository currently contains scaffolding only; none of the request behavior below is implemented yet.

## Immediate user outcome

A demo user can:

1. create an internal request with the minimum business information;
2. see a newest-first list of requests;
3. open a request and see its complete saved information.

The slice should make the Laravel/Vue boundary easy to explain in five minutes: browser form, API validation, application action, Eloquent persistence, JSON response, and Vue rendering.

## Proposed minimum request record

The first implementation should keep one request record with:

- server-generated identifier;
- title;
- description;
- requested amount, serialized as a decimal string;
- ISO 4217 currency code;
- creation and update timestamps.

The exact identifier strategy and whether a human-readable reference is required remain decisions to confirm before feature implementation. Request type, priority, workflow status, organization, requester identity, and subtype detail tables belong to later product decisions, not this first slice.

## Success criteria

- Valid form input creates one durable record through the API.
- Invalid input produces field-level errors that the SPA can show.
- The list displays persisted requests in a stable newest-first order.
- A direct detail URL loads the selected request or a clear not-found state.
- Refreshing the SPA does not lose persisted records.
- The walkthrough can show one focused backend test and a successful frontend production build.

## Explicitly outside the first implementation

- sign-in, sessions, API tokens, users, roles, and permissions;
- approval or rejection workflows and status transitions;
- file attachments and comments;
- notifications, queues, email, and real-time updates;
- immutable event history or advanced auditing;
- organization switching and complete tenant isolation;
- editing, deleting, searching, filtering, pagination, and reporting;
- purchase/reimbursement subtype workflows and execution tracking.

These exclusions prevent scaffolded packages such as Sanctum, queues, and Pinia from being mistaken for implemented product capabilities.

## Relationship to the data dictionary

`TraceReq_diccionario_datos_v1.md` remains useful as a possible long-term domain reference, but it describes a substantially larger PostgreSQL, authentication, organization, approval, attachment, and audit design. It is not the immediate delivery scope. Any later adoption of that design should be decided incrementally and reconciled with the working code and migrations.
