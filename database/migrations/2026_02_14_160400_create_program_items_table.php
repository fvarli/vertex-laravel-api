<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedSmallInteger('order_no');
            $table->string('exercise', 160);
            $table->unsignedSmallInteger('sets')->nullable();
            $table->unsignedSmallInteger('reps')->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'day_of_week', 'order_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_items');
    }
};
