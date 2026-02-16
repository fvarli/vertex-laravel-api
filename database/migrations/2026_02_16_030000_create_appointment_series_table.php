<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('location')->nullable();
            $table->json('recurrence_rule');
            $table->date('start_date');
            $table->time('starts_at_time');
            $table->time('ends_at_time');
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->index(['workspace_id', 'trainer_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_series');
    }
};
