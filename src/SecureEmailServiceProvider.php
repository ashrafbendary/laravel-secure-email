<?php
// src/SecureEmailServiceProvider.php

namespace AshrafBendary\SecureEmail;

use Illuminate\Support\ServiceProvider;
use AshrafBendary\SecureEmail\Services\SecureEmailService;
use AshrafBendary\SecureEmail\Commands\InstallSecureEmailCommand;

class SecureEmailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/Config/secure-email.php',
            'secure-email'
        );

        // Register the service
        $this->app->singleton('secure-email', function ($app) {
            return new SecureEmailService();
        });

        $this->app->alias('secure-email', SecureEmailService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/Config/secure-email.php' => config_path('secure-email.php'),
        ], 'secure-email-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/Migrations/' => database_path('migrations'),
        ], 'secure-email-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/Migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallSecureEmailCommand::class,
            ]);
        }
    }
}