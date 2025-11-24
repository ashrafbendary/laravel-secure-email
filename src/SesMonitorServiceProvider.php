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
            __DIR__.'/../config/ses-monitor.php',
            'ses-monitor'
        );

        $this->app->singleton('ses-monitor', function ($app) {
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
        if (config('ses-monitor.enabled', true)) {
            Route::group([
                'prefix' => config('ses-monitor.route_prefix', 'aws/sns/ses'),
                'middleware' => config('ses-monitor.route_middleware', ['api']),
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
                __DIR__.'/../config/ses-monitor.php' => config_path('ses-monitor.php'),
            ], 'ses-monitor-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'ses-monitor-migrations');
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
        if (config('ses-monitor.enabled', true)) {
            Event::listen(MessageSending::class, CheckEmailBeforeSending::class);
        }
    }
}
