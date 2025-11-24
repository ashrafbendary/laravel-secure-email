<?php
// src/Commands/InstallSecureEmailCommand.php

namespace AshrafBendary\SecureEmail\Commands;

use Illuminate\Console\Command;

class InstallSecureEmailCommand extends Command
{
    protected $signature = 'secure-email:install';
    protected $description = 'Install Laravel Secure Email package';

    public function handle()
    {
        $this->info('Installing Laravel Secure Email...');

        // Publish config
        $this->call('vendor:publish', [
            '--tag' => 'secure-email-config',
            '--force' => true,
        ]);

        // Publish migrations
        $this->call('vendor:publish', [
            '--tag' => 'secure-email-migrations',
            '--force' => true,
        ]);

        // Run migrations
        if ($this->confirm('Do you want to run migrations now?', true)) {
            $this->call('migrate');
        }

        $this->info('✅ Laravel Secure Email installed successfully!');
        $this->line('');
        $this->line('Next steps:');
        $this->line('1. Add ZEROBOUNCE_API_KEY to your .env file');
        $this->line('2. Configure AWS SES credentials');
        $this->line('3. Set up SNS topics for bounces and complaints');
        $this->line('4. Configure webhook URLs in AWS SNS');
    }
}