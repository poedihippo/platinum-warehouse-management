<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom-kolom ini sudah ada di database produksi dan dipakai aplikasi, tetapi tidak pernah
     * dibuatkan migration. Akibatnya instalasi baru (termasuk test suite yang menjalankan
     * migrate:fresh) tidak memiliki kolom tersebut, sementara kode sudah mengaksesnya.
     *
     * Migration ini diletakkan sebelum 2026_10_23_000002 karena index stok tersedia
     * membutuhkan kolom stocks.is_stock.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'article_url')) {
                $table->text('article_url')->nullable()->after('name');
            }

            if (! Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable()->after('article_url');
            }
        });

        Schema::table('product_brands', function (Blueprint $table) {
            if (! Schema::hasColumn('product_brands', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
        });

        Schema::table('product_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('product_categories', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
        });

        Schema::table('product_units', function (Blueprint $table) {
            if (! Schema::hasColumn('product_units', 'description')) {
                $table->text('description')->nullable()->after('price');
            }

            if (! Schema::hasColumn('product_units', 'is_auto_stock')) {
                $table->boolean('is_auto_stock')->default(false)->after('code');
            }
        });

        Schema::table('adjustment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('adjustment_requests', 'expired_date')) {
                $table->date('expired_date')->nullable()->after('description');
            }
        });

        Schema::table('stocks', function (Blueprint $table) {
            if (! Schema::hasColumn('stocks', 'is_stock')) {
                $table->unsignedTinyInteger('is_stock')->nullable()->default(1)->after('id');
            }
        });
    }

    public function down(): void
    {
        // Intentionally a no-op. These columns predate this migration in production and hold
        // real data; dropping them on rollback would destroy data the application still uses.
    }
};
