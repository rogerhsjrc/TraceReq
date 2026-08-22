<?php

namespace App\Domain\Requests;

use InvalidArgumentException;

final readonly class Money
{
    private string $amount;

    private string $currencyCode;

    public function __construct(string $amount, string $currencyCode)
    {
        if (preg_match('/\A\d{1,15}(?:\.\d{1,4})?\z/', $amount) !== 1) {
            throw new InvalidArgumentException('Amount must be a positive decimal string with up to four decimal places.');
        }

        [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad($fraction, 4, '0');

        if ($integer === '0' && trim($fraction, '0') === '') {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $currencyCode = strtoupper($currencyCode);

        if (preg_match('/\A[A-Z]{3}\z/', $currencyCode) !== 1) {
            throw new InvalidArgumentException('Currency code must contain exactly three ASCII letters.');
        }

        $this->amount = $integer.'.'.$fraction;
        $this->currencyCode = $currencyCode;
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }
}
