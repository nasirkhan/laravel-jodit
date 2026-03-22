<?php

namespace Nasirkhan\LaravelJodit;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class JoditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'jodit');

        Blade::componentNamespace('Nasirkhan\\LaravelJodit\\View\\Components', 'jodit');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/jodit.php' => config_path('jodit.php'),
            ], 'jodit-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/jodit'),
            ], 'jodit-views');
        }

        if (config('jodit.route.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/jodit.php', 'jodit');
    }
}
