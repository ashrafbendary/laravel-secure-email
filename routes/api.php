<?php

use Illuminate\Support\Facades\Route;

$controller = config('secure-email.controller');
$routes = config('secure-email.routes');

// Bounce notifications route
Route::post($routes['bounces'], [$controller, 'handleBounce'])
    ->name('secure-email.bounces');

// Complaint notifications route
Route::post($routes['complaints'], [$controller, 'handleComplaint'])
    ->name('secure-email.complaints');

// Delivery notifications route
Route::post($routes['deliveries'], [$controller, 'handleDelivery'])
    ->name('secure-email.deliveries');
