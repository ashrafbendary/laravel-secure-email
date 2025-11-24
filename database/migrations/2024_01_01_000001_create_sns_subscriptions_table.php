<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('ses-monitor.database_connection');
        $tableName = config('ses-monitor.table_names.subscriptions', 'sns_subscriptions');

        Schema::connection($connection)->create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('topic_arn')->unique();
            $table->string('subscription_arn')->nullable();
            $table->string('type')->index(); // bounces, complaints, deliveries
            $table->text('subscribe_url')->nullable();
            $table->string('token')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'confirmed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('ses-monitor.database_connection');
        $tableName = config('ses-monitor.table_names.subscriptions', 'sns_subscriptions');
        
        Schema::connection($connection)->dropIfExists($tableName);
    }
};
