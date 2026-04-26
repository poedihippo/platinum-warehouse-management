<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('stocks', 'expired_date')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->date('expired_date')->nullable()->after('is_tempel');
            });
        }
    }

    public function down(): void
    {
        // Intentionally a no-op. The expired_date column predates this migration
        // in production and holds ~200k rows of real data; dropping it on rollback
        // would destroy data the application still depends on.
    }
};
