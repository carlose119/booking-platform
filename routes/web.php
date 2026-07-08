<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{tenant}/book', BookingController::class)
    ->name('booking.show');

Route::post('/webhooks/stripe/{tenant}', WebhookController::class)
    ->name('stripe.webhook')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
