<?php

use App\Domain\Requests\Money;

it('normalizes valid amounts to four decimal places', function (string $input, string $expected) {
    $money = new Money($input, 'usd');

    expect($money->amount())->toBe($expected)
        ->and($money->currencyCode())->toBe('USD');
})->with([
    'integer string' => ['1', '1.0000'],
    'fractional string' => ['10.5', '10.5000'],
    'leading zeroes' => ['00012.3', '12.3000'],
    'four decimals' => ['0.0100', '0.0100'],
]);

it('rejects invalid amounts', function (string $amount) {
    expect(fn () => new Money($amount, 'USD'))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => '',
    'zero' => '0',
    'zero with decimals' => '0.0000',
    'negative' => '-1',
    'excessive precision' => '1.23456',
    'excessive integer digits' => '1234567890123456',
    'scientific notation' => '1e3',
    'comma separator' => '1,50',
]);

it('rejects invalid currency codes', function (string $currencyCode) {
    expect(fn () => new Money('1.00', $currencyCode))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'too short' => 'US',
    'too long' => 'USDD',
    'contains a number' => 'US1',
    'contains non-ASCII letters' => 'EU€',
]);
