<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4 reshape of the Phase 1 redemptions stub. The stub was never
     * written to by any code (no redemption endpoints existed), so this is
     * a pure schema reshape — no data backfill required.
     *
     * Changes:
     *   - point_cost -> points_spent (snapshot of prize.points_cost)
     *   - status enum -> plain string; new states approved/rejected added,
     *     unused 'cancelled' dropped (transitions validated in controllers)
     *   - quantity, recipient_notes, rejection_reason, shipping_carrier,
     *     submitted_at, reviewed_at, reviewed_by added
     *   - indexes on status + submitted_at for the admin queue
     *     (loyalty_user_id is already indexed by its foreign key)
     */
    public function up(): void
    {
        // doctrine/dbal cannot introspect a MySQL enum column, which breaks
        // renameColumn()/change() on this table. Map enum -> string first.
        $this->mapEnumToString();

        Schema::table('redemptions', function (Blueprint $table) {
            $table->renameColumn('point_cost', 'points_spent');
        });

        Schema::table('redemptions', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();

            $table->integer('quantity')->default(1)->after('points_spent');
            $table->text('recipient_notes')->nullable()->after('recipient_address');
            $table->text('rejection_reason')->nullable()->after('recipient_notes');
            $table->string('shipping_carrier')->nullable()->after('tracking_number');
            $table->timestamp('submitted_at')->nullable()->after('shipping_carrier');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('redemptions', function (Blueprint $table) {
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('redemptions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['submitted_at']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'quantity',
                'recipient_notes',
                'rejection_reason',
                'shipping_carrier',
                'submitted_at',
                'reviewed_at',
            ]);
        });

        Schema::table('redemptions', function (Blueprint $table) {
            $table->renameColumn('points_spent', 'point_cost');
        });
    }

    /**
     * Register the doctrine 'enum' -> 'string' type mapping so dbal-backed
     * schema operations don't choke on the existing status enum. No-op on
     * drivers without a doctrine platform.
     */
    private function mapEnumToString(): void
    {
        try {
            DB::connection()->getDoctrineConnection()
                ->getDatabasePlatform()
                ->registerDoctrineTypeMapping('enum', 'string');
        } catch (\Throwable $e) {
            // Driver without doctrine support (or already mapped) — ignore.
        }
    }
};
