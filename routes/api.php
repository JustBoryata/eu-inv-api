<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OrganisationController;
use App\Http\Controllers\Api\VatRateController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::get('organisation', [OrganisationController::class, 'show']);
    Route::patch('organisation', [OrganisationController::class, 'update']);

    Route::get('vat-rates', [VatRateController::class, 'index']);

    Route::apiResource('clients', ClientController::class);
    Route::apiResource('invoices', InvoiceController::class);
});
