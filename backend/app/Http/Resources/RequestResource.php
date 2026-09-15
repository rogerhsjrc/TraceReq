<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestResource extends JsonResource
{
    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => (string) $this->title,
            'description' => (string) $this->description,
            'requested_amount' => (string) $this->requested_amount,
            'currency_code' => strtoupper((string) $this->currency_code),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'status' => $this->status,
        ];
    }
}
