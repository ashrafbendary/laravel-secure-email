<?php

use Illuminate\Support\Facades\Route;

$controller = config('ses-monitor.controller');
$routes = config('ses-monitor.routes');

// Bounce notifications route
Route::post($routes['bounces'], [$controller, 'handleBounce'])
    ->name('ses-monitor.bounces');

// Complaint notifications route
Route::post($routes['complaints'], [$controller, 'handleComplaint'])
    ->name('ses-monitor.complaints');

// Delivery notifications route
Route::post($routes['deliveries'], [$controller, 'handleDelivery'])
    ->name('ses-monitor.deliveries');
