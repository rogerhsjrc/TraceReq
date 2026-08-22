<?php

namespace App\Application\Requests;

use App\Domain\Requests\Money;
use App\Models\TraceRequest;

final class CreateRequest
{
    public function __invoke(CreateRequestData $data): TraceRequest
    {
        $money = new Money($data->requestedAmount, $data->currencyCode);

        return TraceRequest::query()->create([
            'title' => $data->title,
            'description' => $data->description,
            'requested_amount' => $money->amount(),
            'currency_code' => $money->currencyCode(),
        ]);
    }
}
