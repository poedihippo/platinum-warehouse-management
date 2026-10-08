<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_orders', 'marketplace')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->string('marketplace', 20)->nullable()->after('type');
            });
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable(false)->change();
        });

        if (Schema::hasColumn('sales_orders', 'marketplace')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropColumn('marketplace');
            });
        }
    }
};
