<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\StripeConnectController;
use App\Http\Controllers\WebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{tenant}/book', BookingController::class)
    ->name('booking.show');

Route::middleware('auth')->group(function (): void {
    Route::get('/stripe/connect/start', [StripeConnectController::class, 'start'])
        ->name('stripe.connect.start');
    Route::get('/stripe/connect/callback', [StripeConnectController::class, 'callback'])
        ->name('stripe.connect.callback');
});

Route::post('/webhooks/stripe/connect', [WebhookController::class, 'connect'])
    ->name('stripe.webhook.connect')
    ->withoutMiddleware([ValidateCsrfToken::class]);

Route::post('/webhooks/stripe/{tenant}', WebhookController::class)
    ->name('stripe.webhook')
    ->withoutMiddleware([ValidateCsrfToken::class]);
