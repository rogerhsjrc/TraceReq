<?php

namespace App\Application\Requests;

use App\Models\TraceRequest;

final class ViewRequest
{
    public function __invoke(string $id): TraceRequest
    {
        return TraceRequest::query()->findOrFail($id);
    }
}
