<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 24)->default('whatsapp');
            $table->dateTime('scheduled_for');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('marked_sent_at')->nullable();
            $table->foreignId('marked_sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['appointment_id', 'channel', 'scheduled_for'], 'appointment_reminders_unique_slot');
            $table->index(['workspace_id', 'status', 'scheduled_for'], 'appointment_reminders_workspace_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};
