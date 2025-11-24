<?php

namespace AshrafBendary\SecureEmail;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\Events\MessageSending;
use AshrafBendary\SecureEmail\Console\SubscribeUrlCommand;
use AshrafBendary\SecureEmail\Listeners\CheckEmailBeforeSending;

class SesMonitorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/secure-email.php',
            'secure-email'
        );

        $this->app->singleton('secure-email', function ($app) {
            return new SesMonitorService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        
        $this->registerRoutes();
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerEventListeners();
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        if (config('secure-email.enabled', true)) {
            Route::group([
                'prefix' => config('secure-email.route_prefix', 'aws/sns/ses'),
                'middleware' => config('secure-email.route_middleware', ['api']),
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/secure-email.php' => config_path('secure-email.php'),
            ], 'secure-email-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'secure-email-migrations');
        }
    }

    /**
     * Register the package commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SubscribeUrlCommand::class,
            ]);
        }
    }

    /**
     * Register event listeners.
     */
    protected function registerEventListeners(): void
    {
        if (config('secure-email.enabled', true)) {
            Event::listen(MessageSending::class, CheckEmailBeforeSending::class);
        }
    }
}
