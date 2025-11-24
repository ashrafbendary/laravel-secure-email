<?php
// src/Migrations/2025_01_01_000000_create_secure_email_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $blacklistTable = config('secure-email.tables.blacklist', 'email_blacklist');
        $notificationsTable = config('secure-email.tables.notifications', 'ses_notifications');

        Schema::create($blacklistTable, function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->enum('reason', ['bounce', 'complaint', 'invalid', 'manual']);
            $table->enum('bounce_type', ['hard', 'soft', 'undetermined'])->nullable();
            $table->integer('bounce_count')->default(1);
            $table->json('details')->nullable();
            $table->timestamp('last_bounce_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('reason');
        });

        Schema::create($notificationsTable, function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->nullable();
            $table->string('email');
            $table->enum('type', ['bounce', 'complaint', 'delivery']);
            $table->string('status');
            $table->json('raw_notification');
            $table->timestamps();

            $table->index('email');
            $table->index('type');
            $table->index('message_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('secure-email.tables.blacklist', 'email_blacklist'));
        Schema::dropIfExists(config('secure-email.tables.notifications', 'ses_notifications'));
    }
};