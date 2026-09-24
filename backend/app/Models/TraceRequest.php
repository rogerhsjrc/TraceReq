<?php

namespace App\Models;

use App\Domain\Requests\RequestStatus;
use Database\Factories\TraceRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TraceRequest extends Model
{
    /** @use HasFactory<TraceRequestFactory> */
    use HasFactory, HasUlids;

    protected $table = 'trace_requests';

    protected $fillable = [
        'title',
        'description',
        'requested_amount',
        'currency_code',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:4',
            'status' => RequestStatus::class,
        ];
    }
}
