<?php

use App\Domain\Requests\RequestStatus;
use App\Models\TraceRequest;

it('submits the existing draft without changing its business fields', function () {
    $request = TraceRequest::factory()->create([
        'title' => 'Office chairs',
        'description' => 'Replace worn chairs.',
        'requested_amount' => '1500.0000',
        'currency_code' => 'USD',
        'status' => RequestStatus::Draft,
    ]);
    $created_at = $request->created_at->toISOString();

    $this->postJson("api/requests/{$request->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.id', $request->id)
        ->assertJsonPath('data.status', RequestStatus::Submitted)
        ->assertJsonPath('data.title', $request->title)
        ->assertJsonPath('data.description', $request->description)
        ->assertJsonPath('data.requested_amount', $request->requested_amount)
        ->assertJsonPath('data.currency_code', $request->currency_code)
        ->assertJsonPath('data.created_at', $created_at);

    $this->assertDatabaseCount('trace_requests', 1);
    $this->assertDatabaseHas('trace_requests', [
        'id' => $request->id,
        'title' => $request->title,
        'description' => $request->description,
        'requested_amount' => $request->requested_amount,
        'currency_code' => $request->currency_code,
        'status' => RequestStatus::Submitted->value,
    ]);
    expect($request->fresh()->status)->toBe(RequestStatus::Submitted);

    $this->getJson("/api/requests/{$request->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    $this->getJson('/api/requests')
        ->assertok()
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
    $this->postJson("api/requests/{$id}/submit")
        ->assertNotFound()
        ->assertJsonStructure(['message']);
})->with([
    'missing ULID' => '01J00000000000000000000000',
    'malformed ID' => 'not-a-ulid',
]);
