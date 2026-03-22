<?php

use Illuminate\Support\Facades\Route;
use Nasirkhan\LaravelJodit\Http\Controllers\JoditConnectorController;

Route::middleware(config('jodit.route.middleware', ['web', 'auth']))
    ->prefix(config('jodit.route.prefix', 'jodit'))
    ->group(function () {
        Route::any('connector', [JoditConnectorController::class, 'handle'])
            ->name(config('jodit.route.name', 'jodit.connector'));
    });
