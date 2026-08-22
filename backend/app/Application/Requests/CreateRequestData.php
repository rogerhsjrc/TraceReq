<?php

namespace App\Application\Requests;

final readonly class CreateRequestData
{
    public function __construct(
        public string $title,
        public string $description,
        public string $requestedAmount,
        public string $currencyCode,
    ) {}
}
