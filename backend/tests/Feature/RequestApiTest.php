<?php

use App\Models\TraceRequest;
use Illuminate\Support\Str;

const REQUEST_RESOURCE_FIELDS = [
    'id',
    'title',
    'description',
    'requested_amount',
    'currency_code',
    'created_at',
    'updated_at',
];

it('creates a request with the exact normalized resource representation', function () {
    $response = $this->postJson('/api/requests', [
        'title' => '  New laptops  ',
        'description' => '  Replace two development laptops.  ',
        'requested_amount' => '0002500.5',
        'currency_code' => ' usd ',
    ]);

    $response->assertCreated();

    $resource = $response->json('data');

    expect(array_keys($resource))->toBe(REQUEST_RESOURCE_FIELDS)
        ->and(Str::isUlid($resource['id']))->toBeTrue()
        ->and($resource['title'])->toBe('New laptops')
        ->and($resource['description'])->toBe('Replace two development laptops.')
        ->and($resource['requested_amount'])->toBe('2500.5000')
        ->and($resource['currency_code'])->toBe('USD')
        ->and($resource['created_at'])->toBeString()
        ->and($resource['updated_at'])->toBeString();

    $this->assertDatabaseHas('trace_requests', [
        'id' => $resource['id'],
        'title' => 'New laptops',
        'description' => 'Replace two development laptops.',
        'requested_amount' => '2500.5000',
        'currency_code' => 'USD',
    ]);
});

it('returns validation errors for missing required fields', function () {
    $this->postJson('/api/requests', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'title',
            'description',
            'requested_amount',
            'currency_code',
        ]);
});

it('rejects whitespace-only required text fields', function (string $field) {
    $this->postJson('/api/requests', validRequestPayload([
        $field => '   ',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'title' => 'title',
    'description' => 'description',
]);

it('rejects titles longer than 180 characters', function () {
    $this->postJson('/api/requests', validRequestPayload([
        'title' => str_repeat('a', 181),
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');
});

it('accepts a title containing exactly 180 characters', function () {
    $this->postJson('/api/requests', validRequestPayload([
        'title' => str_repeat('a', 180),
    ]))
        ->assertCreated()
        ->assertJsonPath('data.title', str_repeat('a', 180));
});

it('rejects invalid decimal string amounts', function (string $amount) {
    $this->postJson('/api/requests', validRequestPayload([
        'requested_amount' => $amount,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('requested_amount');
})->with([
    'zero' => '0',
    'zero with scale' => '0.0000',
    'multiple zeroes' => '00',
    'multiple zeroes with two decimals' => '00.00',
    'multiple zeroes with four decimals' => '000.0000',
    'negative' => '-1',
    'scientific notation' => '1e3',
    'comma separator' => '1,50',
    'too many decimals' => '1.23456',
    'too many integer digits' => '1234567890123456',
]);

it('rejects numeric JSON amounts', function (int|float $amount) {
    $this->postJson('/api/requests', validRequestPayload([
        'requested_amount' => $amount,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('requested_amount');
})->with([
    'integer' => 10,
    'float' => 10.5,
]);

it('rejects invalid currency codes', function (string $currencyCode) {
    $this->postJson('/api/requests', validRequestPayload([
        'currency_code' => $currencyCode,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('currency_code');
})->with([
    'too short' => 'US',
    'too long' => 'USDD',
    'number' => 'US1',
    'non-ASCII' => 'EU€',
]);

it('lists requests newest first with id as a deterministic tie-breaker', function () {
    TraceRequest::factory()->create([
        'id' => '01J00000000000000000000001',
        'title' => 'Oldest',
        'created_at' => '2026-08-20 10:00:00',
        'updated_at' => '2026-08-20 10:00:00',
    ]);
    TraceRequest::factory()->create([
        'id' => '01J00000000000000000000002',
        'title' => 'Same time lower id',
        'created_at' => '2026-08-21 10:00:00',
        'updated_at' => '2026-08-21 10:00:00',
    ]);
    TraceRequest::factory()->create([
        'id' => '01J00000000000000000000003',
        'title' => 'Same time higher id',
        'created_at' => '2026-08-21 10:00:00',
        'updated_at' => '2026-08-21 10:00:00',
    ]);

    $response = $this->getJson('/api/requests')->assertOk();
    $resources = $response->json('data');

    expect(array_column($resources, 'id'))->toBe([
        '01J00000000000000000000003',
        '01J00000000000000000000002',
        '01J00000000000000000000001',
    ]);

    foreach ($resources as $resource) {
        expect(array_keys($resource))->toBe(REQUEST_RESOURCE_FIELDS)
            ->and($resource['requested_amount'])->toMatch('/^\d+\.\d{4}$/')
            ->and($resource['currency_code'])->toMatch('/^[A-Z]{3}$/');
    }
});

it('returns request detail with the same resource representation', function () {
    $request = TraceRequest::factory()->create([
        'requested_amount' => '42.5000',
        'currency_code' => 'ARS',
    ]);

    $response = $this->getJson("/api/requests/{$request->id}")->assertOk();
    $resource = $response->json('data');

    expect(array_keys($resource))->toBe(REQUEST_RESOURCE_FIELDS)
        ->and($resource['id'])->toBe($request->id)
        ->and($resource['requested_amount'])->toBe('42.5000')
        ->and($resource['currency_code'])->toBe('ARS');
});

it('returns a JSON 404 for a nonexistent valid ULID', function () {
    $this->getJson('/api/requests/01J00000000000000000000000')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('returns a JSON 404 for a malformed identifier', function () {
    $this->getJson('/api/requests/not-a-ulid')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

function validRequestPayload(array $overrides = []): array
{
    return array_replace([
        'title' => 'Office chairs',
        'description' => 'Replace worn chairs in the development area.',
        'requested_amount' => '1500.00',
        'currency_code' => 'USD',
    ], $overrides);
}
