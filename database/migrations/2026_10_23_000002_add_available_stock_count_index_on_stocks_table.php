<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'stocks_available_count_index';

    private const REDUNDANT_INDEX = 'stocks_stock_product_unit_id_foreign';

    /**
     * Menopang hitungan stok tersedia (StockController@index, @show dan
     * SalesOrderController::productUnitsNew):
     *
     *   select count(*) from stocks
     *   where stock_product_unit_id = ?
     *     and is_stock = 1
     *     and description is null
     *     and deleted_at is null
     *
     * Kolom id (PK) ikut terbawa otomatis di index sekunder InnoDB, jadi
     * pengecekan NOT EXISTS ke sales_order_items tidak perlu membaca row stocks.
     *
     * Index lama `stocks_stock_product_unit_id_foreign` (satu kolom) di-drop
     * karena hanya prefix dari index ini: tidak menambah kemampuan query,
     * tapi menambah biaya tulis dan ~20 MB penyimpanan. Constraint FK-nya
     * tetap utuh karena index baru juga berawalan stock_product_unit_id.
     */
    public function up(): void
    {
        if (! Schema::hasIndex('stocks', self::INDEX)) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->index(
                    ['stock_product_unit_id', 'is_stock', 'deleted_at', 'description'],
                    self::INDEX
                );
            });
        }

        // Di-drop setelah index pengganti ada, supaya FK tetap punya index.
        if (Schema::hasIndex('stocks', self::REDUNDANT_INDEX)) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->dropIndex(self::REDUNDANT_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('stocks', self::REDUNDANT_INDEX)) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->index('stock_product_unit_id', self::REDUNDANT_INDEX);
            });
        }

        if (Schema::hasIndex('stocks', self::INDEX)) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }
    }
};
