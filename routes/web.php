<?php

use Checkin\Http\Controllers\CallbackController;
use Checkin\Http\Controllers\LogoutController;
use Checkin\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('checkin.routes.prefix', 'auth/checkin'),
    'middleware' => config('checkin.routes.middleware', ['web']),
    'as' => 'checkin.',
], function (): void {
    Route::get('redirect', RedirectController::class)->name('redirect');
    Route::get('callback', CallbackController::class)->name('callback');
    Route::match(['get', 'post'], 'logout', LogoutController::class)->name('logout');
});
