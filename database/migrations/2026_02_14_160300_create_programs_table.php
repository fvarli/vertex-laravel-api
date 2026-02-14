<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('trainer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('goal')->nullable();
            $table->date('week_start_date');
            $table->string('status', 16)->default('draft');
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['student_id', 'week_start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
