<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('secret', 64);
            $table->json('events'); // ["appointment.created", "appointment.updated", ...]
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
