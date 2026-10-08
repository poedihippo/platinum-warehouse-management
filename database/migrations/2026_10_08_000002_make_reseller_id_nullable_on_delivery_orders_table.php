<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropForeign(['reseller_id']);
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->change();
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->foreign('reseller_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropForeign(['reseller_id']);
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable(false)->change();
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->foreign('reseller_id')->references('id')->on('users');
        });
    }
};
