---
name: laravel-migration
description: Use when creating new database migrations, modifying table schemas, or adding columns in this project. Front-load keywords: create migration, add column, foreign key, table schema, database structure, alter table, create table.
---

# Laravel Migration Skill

## Project Conventions

This project has 55+ tables across 17 groups. Always follow these conventions when creating migrations.

### File Location
- Migrations: `database/migrations/`

### Naming Convention
```
YYYY_MM_DD_HHMMSS_create_{table_name}_table.php
YYYY_MM_DD_HHMMSS_add_{column}_to_{table_name}_table.php
```

### Standard Create Table Template

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('your_models', function (Blueprint $table) {
            $table->id(); // bigint auto-increment (default)
            // OR for ULID tables (stocks, loyalty tables):
            // $table->ulid('id')->primary();

            // Foreign keys
            $table->foreignId('user_id')->constrained();
            $table->foreignId('warehouse_id')->nullable()->constrained();
            // For nullable FK without ON DELETE:
            // $table->unsignedBigInteger('supplier_id')->nullable();

            // Standard columns
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->integer('qty')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('done_at')->nullable();

            // Enum columns (use string, cast in model)
            $table->string('type', 10)->default('default');
            $table->string('status', 20)->default('pending');

            // JSON columns
            $table->json('raw_source')->nullable();
            $table->json('records')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('your_models');
    }
};
```

### Standard Alter Table Template

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('your_models', function (Blueprint $table) {
            // Add column
            $table->string('new_column', 100)->nullable()->after('existing_column');

            // Add foreign key
            $table->foreignId('related_id')->nullable()->constrained();

            // Add index
            $table->index('status');
            $table->index(['column1', 'column2']); // composite index

            // Modify column (use with caution)
            // $table->string('name', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('your_models', function (Blueprint $table) {
            $table->dropColumn('new_column');
            // $table->dropForeign(['related_id']);
            // $table->dropIndex(['column1', 'column2']);
        });
    }
};
```

### Key Rules

1. **ID Strategy**:
   - `bigint` auto-increment for most tables (default `$table->id()`)
   - ULID for: `stocks`, `loyalty_users`, `claims`, `redemptions`, `prizes`, `points_transactions`
     ```php
     $table->ulid('id')->primary();
     ```

2. **Foreign Keys**:
   - Always use `$table->foreignId('column')->constrained()`
   - Use `->nullable()` for optional relationships
   - Use `->onDelete('cascade')` for parent-owned children
   - Use `->onDelete('restrict')` for audit trail preservation (stocks, loyalty)
   ```php
   // CASCADE (delete children when parent deleted)
   $table->foreignId('sales_order_id')->constrained()->onDelete('cascade');

   // RESTRICT (prevent parent deletion if children exist)
   $table->foreignId('loyalty_user_id')->constrained()->onDelete('restrict');

   // NULL ON DELETE (set null when parent deleted)
   $table->foreignId('prize_category_id')->nullable()->constrained()->nullOnDelete();
   ```

3. **Soft Deletes**: Always add `$table->softDeletes()` for business entities

4. **Timestamps**: Always add `$table->timestamps()`

5. **String Lengths**:
   - `name`: 100 chars
   - `code`: 50 chars
   - `email`: 255 chars
   - `description`: text (unlimited)
   - `status/type`: 10-20 chars

6. **Default Values**:
   - `is_active`: `true`
   - `qty`: `0`
   - `price`: `0`
   - `is_done`: `false`
   - `is_verified`: `false` (or `0`)

7. **Indexing**:
   - Index foreign keys
   - Index columns used in WHERE clauses frequently
   - Composite index for multi-column queries
   ```php
   $table->index('status');
   $table->index(['loyalty_user_id', 'created_at']);
   ```

8. **Guard Migrations**: Use `hasColumn` check for idempotent alter migrations:
   ```php
   if (!Schema::hasColumn('stocks', 'expired_date')) {
       Schema::table('stocks', function (Blueprint $table) {
           $table->date('expired_date')->nullable();
       });
   }
   ```

### Common Column Types

| Use Case | Column Type |
|----------|-------------|
| ID (standard) | `$table->id()` |
| ID (ULID) | `$table->ulid('id')->primary()` |
| Foreign key | `$table->foreignId('x_id')->constrained()` |
| Name/title | `$table->string('name', 100)` |
| Code/number | `$table->string('code', 50)` |
| Description | `$table->text('description')->nullable()` |
| Quantity | `$table->integer('qty')->default(0)` |
| Price | `$table->integer('price')->default(0)` |
| Boolean flag | `$table->boolean('is_active')->default(true)` |
| Timestamp | `$table->timestamp('done_at')->nullable()` |
| Date only | `$table->date('expired_date')->nullable()` |
| JSON data | `$table->json('raw_source')->nullable()` |
| Decimal | `$table->float('amount', 11, 2)->default(0)` |
| Enum (string) | `$table->string('type', 10)->default('default')` |
| ULID reference | `$table->ulid('stock_id')->nullable()` |

### After Creating a Migration

1. Run `php artisan migrate` to apply
2. Check `app/Models/` for matching model updates
3. Run `php artisan db:seed` if seeders needed
