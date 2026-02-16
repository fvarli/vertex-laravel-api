<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_reminders', function (Blueprint $table) {
            $table->dateTime('last_attempted_at')->nullable()->after('attempt_count');
            $table->dateTime('next_retry_at')->nullable()->after('last_attempted_at');
            $table->dateTime('escalated_at')->nullable()->after('next_retry_at');
            $table->string('failure_reason', 255)->nullable()->after('escalated_at');

            $table->index(['status', 'next_retry_at'], 'appointment_reminders_status_retry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_reminders', function (Blueprint $table) {
            $table->dropIndex('appointment_reminders_status_retry_idx');
            $table->dropColumn([
                'last_attempted_at',
                'next_retry_at',
                'escalated_at',
                'failure_reason',
            ]);
        });
    }
};
