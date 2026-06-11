<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4 reshape of the Phase 1 prizes stub.
     *
     * Changes:
     *   - point_cost -> points_cost (Phase 4 naming)
     *   - description / photo_path become nullable (admin may omit)
     *   - stock defaults to 0
     *   - indexes for the "active prizes sorted by cost" + name search
     *
     * prizes has no enum column, so doctrine/dbal change()/renameColumn
     * introspection is safe here without an enum type mapping.
     */
    public function up(): void
    {
        Schema::table('prizes', function (Blueprint $table) {
            $table->renameColumn('point_cost', 'points_cost');
        });

        Schema::table('prizes', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->string('photo_path')->nullable()->change();
            $table->integer('stock')->default(0)->change();
        });

        Schema::table('prizes', function (Blueprint $table) {
            $table->index('name');
            $table->index('points_cost');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('prizes', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['points_cost']);
            $table->dropIndex(['is_active']);
        });

        Schema::table('prizes', function (Blueprint $table) {
            $table->renameColumn('points_cost', 'point_cost');
        });
    }
};
