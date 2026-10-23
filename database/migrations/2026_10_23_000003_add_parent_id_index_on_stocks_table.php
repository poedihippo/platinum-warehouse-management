<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'stocks_parent_id_deleted_at_index';

    /**
     * stocks.parent_id belum punya index sama sekali, padahal dipakai di hampir
     * semua query halaman stock: whereNull('parent_id'), where('parent_id', ...),
     * has('childs') dan doesntHave('childs') (lewat scope isShowGroup).
     *
     * Tanpa index, MySQL mengerjakan NOT EXISTS ke stocks sebagai hash anti-join
     * atas seluruh tabel (376rb baris). Dengan index ini pengecekannya jadi
     * probe per baris: GET /stocks/details untuk product unit besar turun dari
     * ~1 detik ke ~0,3 detik.
     */
    public function up(): void
    {
        if (Schema::hasIndex('stocks', self::INDEX)) {
            return;
        }

        Schema::table('stocks', function (Blueprint $table) {
            $table->index(['parent_id', 'deleted_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('stocks', self::INDEX)) {
            return;
        }

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }
};
