<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'sales_order_items';

    private const COMPOSITE_INDEX = 'sales_order_items_stock_id_is_returned_index';

    /**
     * sales_order_items.stock_id punya dua index identik (duplikat):
     *  - sales_order_items_stock_id_foreign (sisa FK dari migration 2023_05_19)
     *  - sales_order_items_stock_id_index   (dari migration 2026_08_11)
     *
     * Keduanya diganti satu index gabungan (stock_id, is_returned) supaya
     * pengecekan "stock belum terjual" (NOT EXISTS ... and is_returned = 0)
     * bisa dijawab langsung dari index tanpa membaca row sales_order_items.
     * Prefix stock_id tetap melayani semua query lama yang hanya memakai stock_id.
     */
    public function up(): void
    {
        // Dibuat lebih dulu: index dengan stock_id paling depan tetap bisa
        // menopang FK stock_id, sehingga index lamanya boleh di-drop.
        if (! Schema::hasIndex(self::TABLE, self::COMPOSITE_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['stock_id', 'is_returned'], self::COMPOSITE_INDEX);
            });
        }

        if (Schema::hasIndex(self::TABLE, 'sales_order_items_stock_id_index')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('sales_order_items_stock_id_index');
            });
        }

        if (Schema::hasIndex(self::TABLE, 'sales_order_items_stock_id_foreign')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('sales_order_items_stock_id_foreign');
            });
        }
    }

    public function down(): void
    {
        // Index bernama "_foreign" tidak dibuat ulang karena di database ini
        // constraint FK-nya memang sudah tidak ada; index tersebut hanya duplikat.
        if (! Schema::hasIndex(self::TABLE, 'sales_order_items_stock_id_index')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('stock_id', 'sales_order_items_stock_id_index');
            });
        }

        if (Schema::hasIndex(self::TABLE, self::COMPOSITE_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::COMPOSITE_INDEX);
            });
        }
    }
};
