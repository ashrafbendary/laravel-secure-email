<?php
// tests/TestCase.php

namespace AshrafBendary\SecureEmail\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use AshrafBendary\SecureEmail\SecureEmailServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup if needed
        $this->loadMigrationsFrom(__DIR__ . '/../src/Migrations');
    }

    /**
     * Get package providers
     */
    protected function getPackageProviders($app): array
    {
        return [
            SecureEmailServiceProvider::class,
        ];
    }

    /**
     * Get package aliases
     */
    protected function getPackageAliases($app): array
    {
        return [
            'SecureEmail' => \AshrafBendary\SecureEmail\Facades\SecureEmail::class,
        ];
    }

    /**
     * Define environment setup
     */
    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Setup package configuration
        $app['config']->set('secure-email.zerobounce', [
            'api_key' => 'test-api-key',
            'enabled' => true,
            'cache_ttl' => 3600,
        ]);

        $app['config']->set('secure-email.ses', [
            'region' => 'us-east-1',
            'max_bounce_count' => 3,
            'max_complaint_count' => 1,
        ]);

        $app['config']->set('secure-email.blacklist', [
            'auto_blacklist_hard_bounces' => true,
            'auto_blacklist_complaints' => true,
            'soft_bounce_threshold' => 3,
        ]);

        $app['config']->set('secure-email.tables', [
            'blacklist' => 'email_blacklist',
            'notifications' => 'ses_notifications',
        ]);

        // Setup mail configuration
        $app['config']->set('mail.default', 'array');
    }

    /**
     * Run package database migrations
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../src/Migrations');
    }
}