<?php

use App\Http\Controllers\Api\TraceRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/requests', [TraceRequestController::class, 'index']);
Route::post('/requests', [TraceRequestController::class, 'store']);
Route::get('/requests/{id}', [TraceRequestController::class, 'show']);
Route::post('/requests/{id}/submit', [TraceRequestController::class, 'submit']);
