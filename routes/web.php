<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::get('/', function () {
    return Inertia::render('Login');
})->name('home');

Route::get('/login', function () {
    return Inertia::render('Login');
})->name('login');

Route::get('/logout', function () {
    return Inertia::render('Logout');
})->name('logout');

Route::get('/orders', function () {
    return Inertia::render('OrdersOverview');
})->name('orders');

Route::get('/place-order', function () {
    return Inertia::render('LimitOrderForm');
})->name('place-order');
