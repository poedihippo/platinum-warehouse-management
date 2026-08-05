<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tambahkan kolom baru (nullable)
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->foreignId('delivery_order_detail_id')
                ->nullable()
                ->after('sales_order_detail_id');
        });

        // Isi data untuk sales_order_items yang sudah memiliki delivery_order_detail
        DB::statement("
            UPDATE sales_order_items soi
            INNER JOIN delivery_order_details dod
                ON dod.sales_order_detail_id = soi.sales_order_detail_id
            SET soi.delivery_order_detail_id = dod.id
        ");

        // Tambahkan foreign key
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->foreign('delivery_order_detail_id')
                ->references('id')
                ->on('delivery_order_details')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropForeign(['delivery_order_detail_id']);
            $table->dropColumn('delivery_order_detail_id');
        });
    }
};