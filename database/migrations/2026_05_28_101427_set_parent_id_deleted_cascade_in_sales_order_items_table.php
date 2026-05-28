<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            // drop foreign key lama
            $table->dropForeign(['parent_id']);

            // recreate dengan cascade delete
            $table->foreign('parent_id')
                ->references('id')
                ->on('sales_order_items')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
             // drop foreign cascade
            $table->dropForeign(['parent_id']);

            // balikin ke sebelumnya (tanpa cascade)
            $table->foreign('parent_id')
                ->references('id')
                ->on('sales_order_items');
        });
    }
};
