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
        Schema::table('delivery_order_details', function (Blueprint $table) {
            $table->unsignedInteger('qty')->default(0)->after('sales_order_detail_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('delivery_order_details', function (Blueprint $table) {
            $table->dropColumn('qty');
        });
}
};
