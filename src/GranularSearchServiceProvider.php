<?php

namespace Luchmewep\GranularSearch;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class GranularSearchServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'luchmewep');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'luchmewep');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }


        /**
         *
         *
         */
        Arr::macro('isFilled', function (array $haystack, string $needle){
            foreach ($haystack as $key => $value) {
                if($key === $needle) {
                    return empty((string) $value) === false;
                }
            }
            return false;
        });


    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/granular-search.php', 'granular-search');

        // Register the service the package provides.
        $this->app->singleton('granular-search', function ($app) {
            return new GranularSearch;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['granular-search'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/granular-search.php' => config_path('granular-search.php'),
        ], 'granular-search.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/luchmewep'),
        ], 'granular-search.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/luchmewep'),
        ], 'granular-search.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/luchmewep'),
        ], 'granular-search.views');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
