<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('trainer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('full_name', 120);
            $table->string('phone', 32);
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['workspace_id', 'phone']);
            $table->index(['workspace_id', 'status']);
            $table->index(['trainer_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
