---
name: laravel-migration
description: Use when creating database migrations, adding or altering columns, indexes, or foreign keys, or backfilling data in this BEJO Laravel API (MySQL).
---

# Laravel Migration (BEJO)

## Conventions
- Location: `database/migrations/`, anonymous class (`return new class extends Migration`), `up(): void` / `down(): void`.
- Names: `YYYY_MM_DD_HHMMSS_create_{table}_table.php`, `..._add_{column}_to_{table}_table.php`. When two migrations on the same day depend on each other, order them with `_000001`, `_000002` suffixes.
- Laravel 13 Schema API. Money columns: `decimal(total, places)`. Never edit old migrations' DDL: they use `legacyDouble($col, T, P)` (macro in `SchemaServiceProvider`) to keep the Laravel 10 `double(T,P)` columns production has; `float()`/`double()` no longer take total/places and `unsignedFloat()`/`unsignedDecimal()` are gone. `->change()` must restate every attribute (nullable, default, comment) or Laravel drops it.
- MySQL only. Raw MySQL SQL (`UPDATE ... JOIN`, `ALTER ... MODIFY`) is acceptable; guard driver-specific statements with `DB::getDriverName() === 'mysql'` when practical.

## Create table

```php
Schema::create('things', function (Blueprint $table) {
    $table->id();                                   // or $table->ulid('id')->primary() for stock/loyalty tables
    $table->foreignId('warehouse_id')->constrained();
    $table->foreignId('user_id')->nullable()->constrained();
    $table->string('name');
    $table->string('type', 20)->default('default'); // enum values stored as strings, cast in the model
    $table->unsignedInteger('qty')->default(0);
    $table->boolean('is_done')->default(false);
    $table->json('raw_source')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

## Alter table — always guard

Production has columns that were added outside migrations, so alter migrations must be idempotent (see `2026_10_23_000000_add_production_only_columns_to_product_and_stock_tables.php`):

```php
public function up(): void
{
    if (! Schema::hasColumn('stocks', 'expired_date')) {
        Schema::table('stocks', function (Blueprint $table) {
            $table->date('expired_date')->nullable()->after('id');
        });
    }
}

public function down(): void
{
    if (Schema::hasColumn('stocks', 'expired_date')) {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('expired_date');
        });
    }
}
```
Use `Schema::hasTable` the same way for create migrations that may already exist in production.

## Foreign keys
- `->constrained()->cascadeOnDelete()` for owned children (details/items).
- `->restrictOnDelete()` where an audit trail must survive (stock, loyalty points).
- `->nullable()->constrained()->nullOnDelete()` for optional links.
- ULID references: `$table->foreignUlid('stock_id')` / `$table->ulid('stock_id')->nullable()`.

## Data migrations
Backfills go in their own migration (examples: `2026_08_05_133028_...` backfills `sales_order_items.delivery_order_detail_id`; `2026_09_03_000001_...` moves `sales_orders.voucher_id` into the `sales_order_vouchers` pivot). Make them re-runnable and keep `down()` safe.

## After creating a migration
1. Ask the user before running `php8.5 artisan migrate` against their database.
2. Update the model (`$fillable`, `$casts`, relations).
3. Tests run `RefreshDatabase` on `platinum_event_test`, so `php8.5 artisan test` exercises the migration from scratch.
