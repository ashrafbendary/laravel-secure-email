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
        $tableName = config('ses-monitor.table_names.notifications', 'ses_notifications');

        Schema::connection($connection)->create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('type')->index(); // Bounce, Complaint, Delivery
            $table->string('notification_type')->nullable()->index(); // Permanent, Transient, Undetermined (for bounces)
            $table->string('email')->index();
            $table->string('subject')->nullable();
            $table->text('bounce_type')->nullable();
            $table->text('bounce_sub_type')->nullable();
            $table->text('complaint_feedback_type')->nullable();
            $table->json('notification_data');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->index(['email', 'type', 'created_at']);
            $table->index(['email', 'subject', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('ses-monitor.database_connection');
        $tableName = config('ses-monitor.table_names.notifications', 'ses_notifications');
        
        Schema::connection($connection)->dropIfExists($tableName);
    }
};
