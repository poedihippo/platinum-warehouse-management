---
name: laravel-model
description: Use when creating or modifying Eloquent models, relationships, scopes, casts, model hooks, or tenancy in this BEJO Laravel API. Covers booted() hooks, Tenanted scopes, enums, numbering, ULIDs, and soft deletes.
---

# Laravel Model (BEJO)

## Location
- Warehouse/admin models: `app/Models/`
- Loyalty models: `app/Models/Loyalty/` (all ULID)

## Template

```php
<?php

namespace App\Models;

use App\Enums\SomeEnum;
use App\Traits\FilterStartEndDate;
use App\Traits\Tenanted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Thing extends Model
{
    use FilterStartEndDate, SoftDeletes, Tenanted;

    protected $fillable = ['warehouse_id', 'user_id', 'name', 'type', 'is_done'];

    protected $casts = [
        'type' => SomeEnum::class,
        'is_done' => 'boolean',
        'raw_source' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->user_id)) {
                $model->user_id = auth('sanctum')->id();
            }
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(ThingDetail::class);
    }
}
```

## Rules

- Hooks go in `booted()` (not `boot()`); there are no observers. If a hook fires an event, register it in `EventServiceProvider::$listen` (event discovery is off). Heavy listeners `implements ShouldQueue` and must not depend on `auth()`/`request()` — pass what they need through the event.
- **Tenancy**:
  - Model has a `warehouse_id` → `use Tenanted` (gives `scopeTenanted` + `scopeFindTenanted`; Spatie role `admin` bypasses).
  - Child model without `warehouse_id` → copy the pattern used by `Stock` / `ReceiveOrderDetail`: define `scopeTenanted` via `whereHas('<parent>', fn ($q) => $q->tenanted())` and a matching `scopeFindTenanted`.
  - Note `SalesOrder` overrides `scopeTenanted` (SPG-only filter, no warehouse scoping) — don't copy that by accident.
- **Enums**: bensampo enums from `app/Enums/` (`CompanyEnum`, `DocumentType`, `UserType`, `SalesOrderType`, `Marketplace`, `PaymentType`, `DiscountType`, …). Compare with `$model->type->is(UserType::Spg)`; never hardcode the string.
- **Soft deletes**: use `SoftDeletes` for new business entities; `CustomSoftDeletes` (records `deleted_by`) is available. Existing `SalesOrder`, `DeliveryOrder`, `ReceiveOrder` do not soft-delete.
- **ULID** (`HasUlids`) only for `stocks` and loyalty tables. Lowercase ULIDs are the DB convention.
- **Sequential numbers**: never compute "max + 1". Use `NumberingService::next(CompanyEnum, DocumentType)` in a `creating` hook guarded by `NumberingService::enabled()` (see `SalesOrder::booted()` / `DeliveryOrder::booted()`), or a `lockForUpdate` counter row.
- **Stock**: models that change stock expose `histories()` (`morphMany(StockHistory::class, 'model')`) and write a history row for every change; branch on `productUnit->is_generate_qr` (QR rows vs `StockProductUnit.qty`).
- **Factories**: most models don't `use HasFactory`; a factory in `database/factories/` is still welcome — tests call `ThingFactory::new()`.
- Never hardcode warehouse IDs.

## After creating a model

1. Migration (see the `laravel-migration` skill).
2. Relationships on related models.
3. Enum in `app/Enums/` if needed.
4. Update `AGENTS.md` if the model introduces a new flow or tenancy rule.
