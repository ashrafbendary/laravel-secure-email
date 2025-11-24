<?php
// src/Routes/web.php

use Illuminate\Support\Facades\Route;
use AshrafBendary\SecureEmail\Controllers\SesWebhookController;

$prefix = config('secure-email.webhooks.prefix', 'webhooks/ses');
$middleware = config('secure-email.webhooks.middleware', ['api']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::post('/bounce', [SesWebhookController::class, 'handleBounce']);
        Route::post('/complaint', [SesWebhookController::class, 'handleComplaint']);
        Route::post('/delivery', [SesWebhookController::class, 'handleDelivery']);
    });