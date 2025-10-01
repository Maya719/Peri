<?php

use App\Http\Controllers\PayPalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentControllers\StripeController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/stripe/webhook', [StripeController::class, 'handleWebhook']);
Route::post('/paypal/webhook', [PayPalController::class, 'handleWebhook'])->name('paypal.webhook');

