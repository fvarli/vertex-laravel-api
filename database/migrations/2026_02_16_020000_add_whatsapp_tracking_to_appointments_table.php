<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('whatsapp_status', 16)->default('not_sent')->after('status');
            $table->dateTime('whatsapp_marked_at')->nullable()->after('whatsapp_status');
            $table->foreignId('whatsapp_marked_by_user_id')->nullable()->after('whatsapp_marked_at')
                ->constrained('users')->nullOnDelete();

            $table->index(['workspace_id', 'whatsapp_status']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'whatsapp_status']);
            $table->dropConstrainedForeignId('whatsapp_marked_by_user_id');
            $table->dropColumn(['whatsapp_marked_at', 'whatsapp_status']);
        });
    }
};
