<?php

namespace AshrafBendary\SecureEmail;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\Events\MessageSending;
use AshrafBendary\SecureEmail\Console\SubscribeUrlCommand;
use AshrafBendary\SecureEmail\Listeners\CheckEmailBeforeSending;
use AshrafBendary\SecureEmail\Validation\DeliverabilityChecker;
use AshrafBendary\SecureEmail\Validation\EmailValidator;
use AshrafBendary\SecureEmail\Validation\NullValidator;
use AshrafBendary\SecureEmail\Validation\ZeroBounceValidator;
use InvalidArgumentException;

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

        $this->app->singleton(SesMonitorService::class);
        $this->app->alias(SesMonitorService::class, 'secure-email');

        $this->app->singleton(EmailValidator::class, function ($app) {
            $driver = config('secure-email.validation.driver');

            return match (true) {
                $driver === null, $driver === '', $driver === 'null', $driver === 'none' => new NullValidator(),
                $driver === 'zerobounce' => new ZeroBounceValidator((array) config('secure-email.validation.zerobounce', [])),
                default => $this->customValidator((string) $driver),
            };
        });

        $this->app->singleton(DeliverabilityChecker::class, function ($app) {
            return new DeliverabilityChecker(
                $app->make(SesMonitorService::class),
                $app->make(EmailValidator::class),
            );
        });
    }

    /**
     * Resolve a user-supplied validator class name.
     */
    protected function customValidator(string $class): EmailValidator
    {
        $validator = $this->app->make($class);

        if (! $validator instanceof EmailValidator) {
            throw new InvalidArgumentException(sprintf(
                'secure-email.validation.driver must be "zerobounce", null, or a class implementing %s; got %s.',
                EmailValidator::class,
                $class,
            ));
        }

        return $validator;
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'secure-email');

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

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/secure-email'),
            ], 'secure-email-translations');
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
