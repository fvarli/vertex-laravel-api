<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('title', 150);
            $table->text('goal')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'trainer_user_id']);
            $table->unique(['workspace_id', 'trainer_user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_templates');
    }
};
