<?php

use App\Http\Controllers\Api\DispatchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->prefix('dispatch')->group(function () {
    Route::get('/snapshot', [DispatchController::class, 'snapshot'])->name('api.dispatch.snapshot');
    Route::post('/apply',   [DispatchController::class, 'apply'])->name('api.dispatch.apply');
});
