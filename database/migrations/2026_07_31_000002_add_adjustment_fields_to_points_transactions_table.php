<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * adjusted_by/reason support the admin manual points-adjustment
     * feature. Both null for normal earn/spend rows (claim approval,
     * redemption). adjusted_by references users.id (warehouse/admin
     * staff), not loyalty_users — the ledger row itself still belongs to
     * the customer via the existing loyalty_user_id column.
     */
    public function up(): void
    {
        Schema::table('points_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('points_transactions', 'adjusted_by')) {
                $table->foreignId('adjusted_by')->nullable()->after('description')
                    ->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('points_transactions', 'reason')) {
                $table->text('reason')->nullable()->after('adjusted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('points_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('points_transactions', 'reason')) {
                $table->dropColumn('reason');
            }

            if (Schema::hasColumn('points_transactions', 'adjusted_by')) {
                $table->dropConstrainedForeignId('adjusted_by');
            }
        });
    }
};
