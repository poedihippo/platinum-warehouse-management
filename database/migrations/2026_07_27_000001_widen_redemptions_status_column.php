<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repair for databases where redemptions.status is still the original
     * 4-value enum('pending','shipped','delivered','cancelled').
     *
     * RedemptionReviewController writes Redemption::STATUS_APPROVED and
     * STATUS_REJECTED ('approved' / 'rejected'), neither of which is in
     * that enum, so MySQL raises SQLSTATE[01000] "Data truncated for
     * column 'status'" and approve/reject 500s.
     *
     * 2026_06_08_000002 already intended this column to be a plain string
     * ("status enum -> plain string ... transitions validated in
     * controllers"), so this restates that end state as unconditional
     * SQL rather than re-narrowing to a wider enum — a value the model
     * gains later can then never truncate. Idempotent: running it against
     * a column that is already VARCHAR is a no-op.
     *
     * No data is touched; existing rows (including any historical
     * 'cancelled') keep their value.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            // The enum only survives on MySQL. Other drivers (sqlite in
            // tests) already came out of the Phase 4 reshape as a string.
            return;
        }

        DB::statement("ALTER TABLE redemptions MODIFY status VARCHAR(255) NOT NULL DEFAULT 'pending'");
    }

    /**
     * Re-narrows to the original 4-value enum, but only when every row
     * still fits it. If any row holds 'approved' or 'rejected', MySQL
     * would truncate it to '' — so the column is left as VARCHAR instead.
     * That makes this down() a no-op on a database that has served real
     * approvals, which is the intended trade: reversibility must not
     * destroy the review audit trail.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $original = ['pending', 'shipped', 'delivered', 'cancelled'];

        if (DB::table('redemptions')->whereNotIn('status', $original)->exists()) {
            return;
        }

        DB::statement(
            "ALTER TABLE redemptions MODIFY status ENUM('pending','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending'"
        );
    }
};
