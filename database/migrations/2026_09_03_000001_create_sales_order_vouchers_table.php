<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_order_vouchers')) {
            Schema::create('sales_order_vouchers', function (Blueprint $table) {
                $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
                $table->timestamps();

                $table->primary(['sales_order_id', 'voucher_id']);
            });
        }

        // Legacy backfill: link sales orders that carry a single `voucher_code`
        // in `raw_source` to their voucher. `raw_source` is a JSON column.
        // Voucher codes are compared case-insensitively (MySQL default collation).
        $voucherIds = DB::table('vouchers')
            ->get(['id', 'code'])
            ->mapWithKeys(fn ($v) => [mb_strtolower($v->code) => $v->id]);

        DB::table('sales_orders')
            ->whereNotNull('raw_source')
            ->where('raw_source', 'like', '%voucher_code%')
            ->select('id', 'raw_source')
            ->orderBy('id')
            ->chunk(500, function ($orders) use ($voucherIds) {
                foreach ($orders as $order) {
                    $code = json_decode($order->raw_source, true)['voucher_code'] ?? null;

                    if (! is_string($code) || $code === '') {
                        continue;
                    }

                    $voucherId = $voucherIds[mb_strtolower($code)] ?? null;

                    if (! $voucherId) {
                        continue;
                    }

                    DB::table('sales_order_vouchers')->insertOrIgnore([
                        'sales_order_id' => $order->id,
                        'voucher_id' => $voucherId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        if (Schema::hasColumn('sales_orders', 'voucher_id')) {
            if ($this->foreignKeyExists('sales_orders', 'sales_orders_voucher_id_foreign')) {
                Schema::table('sales_orders', function (Blueprint $table) {
                    $table->dropForeign(['voucher_id']);
                });
            }

            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropColumn('voucher_id');
            });
        }

        if (! $this->constraintExists('sales_orders', 'sales_orders_price_non_negative')) {
            DB::statement('ALTER TABLE sales_orders ADD CONSTRAINT sales_orders_price_non_negative CHECK (price >= 0)');
        }
    }

    public function down(): void
    {
        if ($this->constraintExists('sales_orders', 'sales_orders_price_non_negative')) {
            DB::statement('ALTER TABLE sales_orders DROP CONSTRAINT sales_orders_price_non_negative');
        }

        if (! Schema::hasColumn('sales_orders', 'voucher_id')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('voucher_id')->nullable()->after('user_id');
                $table->foreign('voucher_id')->references('id')->on('vouchers');
            });
        }

        // Migrate back: take the first voucher per SO
        DB::statement('UPDATE sales_orders s
            JOIN sales_order_vouchers sov ON sov.sales_order_id = s.id
            SET s.voucher_id = sov.voucher_id
            WHERE sov.voucher_id = (
                SELECT MIN(sov2.voucher_id) FROM sales_order_vouchers sov2 WHERE sov2.sales_order_id = s.id
            )');

        Schema::dropIfExists('sales_order_vouchers');
    }

    private function foreignKeyExists(string $table, string $foreign): bool
    {
        return (bool) DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('constraint_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $foreign)
            ->exists();
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return (bool) DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('constraint_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->exists();
    }
};
