---
name: laravel-model
description: Use when creating new Laravel models, Eloquent relationships, or modifying existing models in this project. Front-load keywords: create model, add relationship, add scope, add cast, add enum, model convention, SoftDeletes, Tenanted trait.
---

# Laravel Model Creation Skill

## Project Conventions

This project follows strict model conventions. Always adhere to these patterns.

### File Location
- Models: `app/Models/` (warehouse/admin models)
- Loyalty models: `app/Models/Loyalty/` (loyalty-specific models)
- Never create models outside these directories.

### Standard Model Template

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Tenanted;
use App\Traits\FilterStartEndDate;

class YourModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'your_models'; // explicit table name

    protected $fillable = [
        'field1',
        'field2',
    ];

    protected $casts = [
        // Use enum classes for enum fields, never hardcode strings
        // 'type' => SomeEnum::class,
        // 'status' => AnotherEnum::class,
        // 'raw_source' => 'array', // for JSON columns
    ];

    // boot() for side effects (auto-numbering, defaults, events)
    protected static function boot()
    {
        parent::boot();

        // Auto-set user_id on creation
        static::creating(function ($model) {
            if (empty($model->user_id)) {
                $model->user_id = auth('sanctum')->id();
            }
        });

        // Default description
        static::creating(function ($model) {
            if (empty($model->description)) {
                $model->description = '#Barang yang sudah dibeli tidak dapat dikembalikan. Terimakasih';
            }
        });
    }

    // Relationships
    // public function parent()
    // {
    //     return $this->belongsTo(ParentModel::class);
    // }

    // public function children()
    // {
    //     return $this->hasMany(ChildModel::class);
    // }
}
```

### Key Rules

1. **Always use SoftDeletes** on new business entity models
2. **Use Tenanted trait** (`app/Traits/Tenanted.php`) on models that should be warehouse-scoped:
   - Required for: StockProductUnit, SalesOrder, DeliveryOrder, ReceiveOrder, StockOpname
   - Provides `scopeTenanted()` and `scopeFindTenanted()`
3. **Use FilterStartEndDate** trait for models with date filtering (orders, receive orders)
4. **Use enum classes** for enum fields:
   - `App\Enums\CompanyEnum` (pas, pa)
   - `App\Enums\UserType` (admin, reseller, customer, etc.)
   - `App\Enums\SalesOrderType` (default, delivery, pickup, free)
   - `App\Enums\PaymentType` (cash, transfer, credit_card, qris)
   - `App\Enums\DiscountType` (nominal, percentage)
5. **Never hardcode warehouse IDs** in model logic
6. **Use ULID** (`HasUlids` trait) only for: stocks, loyalty_users, claims, redemptions, prizes, points_transactions
7. **CustomSoftDeletes** trait for Payment, Voucher, VoucherCategory (tracks `deleted_by`)

### Side Effects Patterns

Common `boot()` side effects used in this project:

```php
// Auto-set user_id from sanctum auth
static::creating(function ($model) {
    if (empty($model->user_id)) {
        $model->user_id = auth('sanctum')->id();
    }
});

// Auto-generate sequential number (use lockForUpdate)
static::created(function ($model) {
    if (empty($model->invoice_no)) {
        $model->invoice_no = $model->getNextInvoiceNumber();
        $model->save();
    }
});

// Fire event on status change
static::updated(function ($model) {
    if ($model->wasChanged('is_done') && $model->is_done) {
        event(new SomeEvent($model));
    }
});
```

### Relationship Patterns

```php
// BelongsTo (standard FK)
public function warehouse()
{
    return $this->belongsTo(Warehouse::class);
}

// HasMany
public function details()
{
    return $this->hasMany(OrderDetail::class);
}

// HasOne
public function payment()
{
    return $this->hasOne(Payment::class);
}

// MorphMany (polymorphic - used by StockHistory, Media)
public function stockHistories()
{
    return $this->morphMany(StockHistory::class, 'model');
}

// BelongsToMany (pivot)
public function warehouses()
{
    return $this->belongsToMany(Warehouse::class, 'user_warehouses');
}

// Self-referential (parent-child)
public function parent()
{
    return $this->belongsTo(Stock::class, 'parent_id');
}

public function children()
{
    return $this->hasMany(Stock::class, 'parent_id');
}
```

### After Creating a Model

1. Run `php artisan make:migration create_{table}_table` (if new table)
2. Add any necessary enums to `app/Enums/`
3. Add relationships to related models
4. Register events in `app/Providers/EventServiceProvider.php` if using custom events
