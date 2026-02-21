<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('pending')->after('owner_user_id');
            $table->timestamp('approval_requested_at')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approval_requested_at');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->text('approval_note')->nullable()->after('approved_by_user_id');

            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropIndex(['approval_status']);
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn([
                'approval_status',
                'approval_requested_at',
                'approved_at',
                'approval_note',
            ]);
        });
    }
};
