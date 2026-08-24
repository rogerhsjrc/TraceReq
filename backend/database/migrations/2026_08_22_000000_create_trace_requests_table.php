<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trace_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 180);
            $table->text('description');
            $table->decimal('requested_amount', 19, 4);
            $table->char('currency_code', 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trace_requests');
    }
};
