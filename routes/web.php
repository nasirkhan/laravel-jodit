<?php

use Illuminate\Support\Facades\Route;
use Nasirkhan\LaravelJodit\Http\Controllers\JoditConnectorController;

Route::middleware(config('jodit.route.middleware', ['web', 'auth', 'throttle:60,1']))
    ->prefix(config('jodit.route.prefix', 'jodit'))
    ->group(function () {
        Route::post('connector', [JoditConnectorController::class, 'handle'])
            ->name(config('jodit.route.name', 'jodit.connector'));
    });
