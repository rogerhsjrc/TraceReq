# Testing draft and submitted requests

This guide covers the proposed extension to the original create/list/view slice. It is a testing plan with example tests, not a claim that these tests are installed or passing. PRODUCT.md, ARCHITECTURE.md, and MVP.md still need to be reconciled with the new status contract.

The application is an unauthenticated local demo, not production-ready.

## 1. Define observable behavior

| Scenario | Expected result |
|---|---|
| Create a valid request | HTTP 201; response and database status are `draft` |
| Create with client-supplied `status: submitted` | Still creates a draft; server owns initial status |
| Submit a draft | HTTP 200; same ID and business fields, status becomes `submitted` |
| Submit an already-submitted request | HTTP 200; no new record and no timestamp change |
| Submit an absent or malformed ID | JSON 404 |
| Read submitted request through list/detail | Both serialize status as the string `submitted` |
| Submission fails in the browser | Details remain visible, error appears, button is enabled again |
| Retry succeeds | Error clears, badge updates, Submit button disappears |
| Navigate while submission is pending | Old result/error cannot replace the new page's state |

These tests cover sequential retries. They do not prove concurrency guarantees.

## 2. Update existing API contract tests

In `backend/tests/Feature/RequestApiTest.php`, append `'status'` to `REQUEST_RESOURCE_FIELDS` after `'updated_at'`, matching the resource's current order. Add `draft` assertions to the create response and `assertDatabaseHas` expectation. The three currently failing tests detect this intentional response-shape change.

Add this test in that same file, where `validRequestPayload()` already exists:

```php
it('creates a draft regardless of client supplied status', function () {
    $response = $this->postJson('/api/requests', validRequestPayload([
        'status' => 'submitted',
    ]));

    $response->assertCreated()->assertJsonPath('data.status', 'draft');

    $this->assertDatabaseHas('trace_requests', [
        'id' => $response->json('data.id'),
        'status' => 'draft',
    ]);
});
```

Also keep a creation assertion without a supplied status; this is the normal UI path.

## 3. Add endpoint tests for submission

Create `backend/tests/Feature/SubmitRequestTest.php`. Existing `tests/Pest.php` already applies Laravel's test case and `RefreshDatabase` to Feature tests; do not duplicate that setup. Exercise the real HTTP endpoint and database rather than mocking the action: this validates routing, the controller, action, enum cast, persistence, and JSON serialization together.

```php
<?php

use App\Domain\Requests\RequestStatus;
use App\Models\TraceRequest;

it('submits the existing draft without changing its business fields', function () {
    $request = TraceRequest::factory()->create([
        'status' => RequestStatus::Draft,
        'title' => 'Office chairs',
        'description' => 'Replace worn chairs.',
        'requested_amount' => '1500.0000',
        'currency_code' => 'USD',
    ]);
    $createdAt = $request->created_at->toISOString();

    $this->postJson("/api/requests/{$request->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.id', $request->id)
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.title', 'Office chairs')
        ->assertJsonPath('data.description', 'Replace worn chairs.')
        ->assertJsonPath('data.requested_amount', '1500.0000')
        ->assertJsonPath('data.currency_code', 'USD')
        ->assertJsonPath('data.created_at', $createdAt);

    $this->assertDatabaseCount('trace_requests', 1);
    $this->assertDatabaseHas('trace_requests', [
        'id' => $request->id,
        'status' => 'submitted',
        'title' => 'Office chairs',
        'description' => 'Replace worn chairs.',
        'requested_amount' => '1500.0000',
        'currency_code' => 'USD',
    ]);
    expect($request->fresh()->status)->toBe(RequestStatus::Submitted);

    $this->getJson("/api/requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    $this->getJson('/api/requests')
        ->assertOk()
        ->assertJsonPath('data.0.id', $request->id)
        ->assertJsonPath('data.0.status', 'submitted');
});

it('leaves the saved record unchanged on a later submission retry', function () {
    $this->travelTo(now()->startOfSecond());

    try {
        $request = TraceRequest::factory()->create([
            'status' => RequestStatus::Draft,
        ]);

        $this->postJson("/api/requests/{$request->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $before = $request->fresh()->getAttributes();
        $this->travel(1)->minutes();

        $this->postJson("/api/requests/{$request->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        expect($request->fresh()->getAttributes())->toBe($before);
        $this->assertDatabaseCount('trace_requests', 1);
    } finally {
        $this->travelBack();
    }
});

it('returns JSON 404 when submitting an unknown request', function (string $id) {
    $this->postJson("/api/requests/{$id}/submit")
        ->assertNotFound()
        ->assertJsonStructure(['message']);
})->with([
    'missing ULID' => '01J00000000000000000000000',
    'malformed ID' => 'not-a-ulid',
]);
```

Advancing the clock makes the retry test meaningful: two updates in the same second could otherwise hide an unintended timestamp write. Test externally observable behavior, not whether a particular private method was called. There is no need for a separate unit test merely listing enum cases.

## 4. Verify the browser flow

No frontend test runner has been selected in this project. Start with these manual checks against the local PostgreSQL application:

1. Create a request. Confirm Draft in the list and detail page, and a visible Submit button.
2. Submit it. Confirm the loading label and disabled button while pending, then Submitted and no Submit button. Refresh and revisit the list to verify persistence.
3. Open a different draft. After details load, set the browser's Network panel to Offline. Submit: the inline alert should appear, details should remain, and the button should become enabled again.
4. Restore networking and retry. The alert must clear when retry starts and stay cleared after success. This exposes the current missing `submitError.value = ''` at the start of `handleSubmit`.
5. Throttle the network, submit draft A, and navigate to request B while the POST is pending. When A completes, B must remain on screen without A's data or error. This exposes the current missing lifecycle guard for submission. An aborted HTTP request may still have been processed by the server; verify A by loading it again.
6. Check the alert with keyboard/screen-reader use, and inspect badge/button layout at narrow widths.

When a frontend runner is deliberately selected, automate these component behaviors using mocked API promises: pending, resolution, rejection, retry, and navigation before resolution. Verify displayed labels, disabled state, and absence of stale updates. Keep real persistence covered by backend tests. Formatter cases should include `draft`, `submitted`, and the chosen unknown-status fallback.

## 5. Run checks and record limits

From `backend/`:

```sh
php artisan test --filter=SubmitRequest
php artisan test
php vendor/bin/pint --test
```

From `frontend/`, using Node 24 LTS, version 24.12.0 or newer:

```sh
npm run build
npm run lint
```

The lint script applies fixes; inspect its diff. For a review without edits, run the installed Oxlint and ESLint binaries without `--fix`.

The existing PHPUnit configuration uses SQLite `:memory:`. These tests do not validate PostgreSQL-specific SQL or concurrency. Use a dedicated disposable PostgreSQL test database and an explicit test configuration before adding PostgreSQL constraints or concurrency guarantees; never point `RefreshDatabase` at a demo database containing data you need to retain.

For migration verification, use a disposable database to check that existing rows acquire `draft`, new records get the default, and rolling back this migration removes only `status`. No production or demo database rollback is needed for this test.
