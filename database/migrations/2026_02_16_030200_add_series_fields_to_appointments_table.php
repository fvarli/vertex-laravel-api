<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('id')->constrained('appointment_series')->nullOnDelete();
            $table->date('series_occurrence_date')->nullable()->after('series_id');
            $table->boolean('is_series_exception')->default(false)->after('series_occurrence_date');
            $table->string('series_edit_scope_applied', 16)->nullable()->after('is_series_exception');

            $table->index(['series_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['series_id', 'starts_at']);
            $table->dropConstrainedForeignId('series_id');
            $table->dropColumn(['series_occurrence_date', 'is_series_exception', 'series_edit_scope_applied']);
        });
    }
};
