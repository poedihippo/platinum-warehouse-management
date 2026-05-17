<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanctum's default personal_access_tokens.tokenable_id is an
 * unsignedBigInteger. App\Models\Loyalty\LoyaltyUser uses 26-char ULID
 * primary keys, so inserting a loyalty token truncated the value (MySQL
 * warning 1265 -> exception in strict mode, HTTP 500 on login).
 *
 * Widen the column to string(36): a varchar holds both the numeric IDs
 * of App\Models\User (warehouse staff) — stored as their string form —
 * and ULIDs, so existing staff tokens keep working. The compound index
 * (tokenable_type, tokenable_id) Sanctum relies on is dropped before the
 * type change and restored after.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['tokenable_type', 'tokenable_id']);
        });
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('tokenable_id', 36)->change();
        });
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['tokenable_type', 'tokenable_id']);
        });
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('tokenable_id')->change();
        });
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }
};
