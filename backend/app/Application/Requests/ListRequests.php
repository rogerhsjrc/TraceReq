<?php

namespace App\Application\Requests;

use App\Models\TraceRequest;
use Illuminate\Database\Eloquent\Collection;

final class ListRequests
{
    /** @return Collection<int, TraceRequest> */
    public function __invoke(): Collection
    {
        return TraceRequest::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
