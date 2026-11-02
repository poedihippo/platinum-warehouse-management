---
name: laravel-test
description: Use when running tests, writing new PHPUnit feature/unit tests, or debugging test failures in this BEJO Laravel API. Covers the MySQL test DB, Sanctum token abilities, inline permissions, factories without HasFactory, and fakes.
---

# Laravel Tests (BEJO)

## Running

```bash
php8.5 artisan test                                        # everything
php8.5 artisan test --filter=DocumentCounterTest           # one class
php8.5 artisan test --filter='NumberingTest::test_name'    # one method
php8.5 artisan test tests/Feature/Loyalty                  # one directory
php8.5 artisan test --testsuite=Unit
```

Environment (`phpunit.xml`): MySQL database `platinum_event_test` is **forced** (create it once, same credentials as `.env`); `QUEUE_CONNECTION=sync` (queued listeners run inline); cache/session/mail are `array`. SQLite won't work. `tests/TestCase.php` refuses any connection that is not MySQL with a database name ending in `_test`. `tests/Feature/ExampleTest` fails by design (`/` redirects). PHPUnit 12: data providers and other metadata are attributes (`#[DataProvider('provider')]`), docblock annotations are ignored. Laravel 13 / PHP 8.5: run with `php8.5`.

## Layout

- `tests/Feature/Api/` — warehouse endpoints, one characterization test per module (`MasterDataTest`, `StockTest`, `ReceiveOrderTest`, `StockOpnameTest`, `DeliveryOrderTest`, `SalesTest`, `AccessTest`, …) plus `DocumentCounterTest`, `InvoiceScanTest`, `ManualInvoiceNumberTest`, `Public/StockVerificationTest`
- `tests/Feature/Loyalty/` (+ `Admin/`) — loyalty customer and admin flows
- `tests/Feature/Security/`, `DangerousRoutesRemovedTest`, `NumberingTest`
- `tests/Unit/` — pure logic (e.g. `Pipes/CalculateAutoDiscountTest` builds orders with `SalesOrder::make()`, no DB), `BaseEnumTest`, and `ContainerBindingsTest` (resolves every `AppServiceProvider::$singletons` binding)

Shared fixtures: `use Tests\Support\InteractsWithWarehouse;` gives `makeUser`, `actingAsAdmin()` (Spatie role `admin` + `['warehouse']` token), `actingAsStaff($permissions, $warehouses, $attrs)`, `makeWarehouse`, `makeProduct`, `makeProductUnit($qr)`, `spu($unit, $warehouse)`, `makeStocks`, `makeGroupedStock`, `makeSalesOrder`, `makeDeliveryOrder`, `histories($model)`, `assertHistory($model, [...])`. Keep fixtures small — tests run on the isolated `platinum_event_test` DB, never on real data.

Behaviour that is a known bug is pinned in a `test_known_quirk_*` test with its exact status/body. Don't "fix" these during a refactor; change the test only when fixing the bug on purpose.

## Warehouse endpoint test

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'thing_create', 'guard_name' => 'web']);
    }

    private function actingAsUser(array $permissions = ['thing_create']): User
    {
        $user = User::factory()->create(['type' => 'admin']);
        $user->givePermissionTo($permissions);

        Sanctum::actingAs($user, ['warehouse']);   // route group requires ability:warehouse

        return $user;
    }

    public function test_user_can_create_thing(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/things', ['name' => 'Test']);

        $response->assertCreated();
        $this->assertDatabaseHas('things', ['name' => 'Test']);
    }

    public function test_user_without_permission_gets_403(): void
    {
        $this->actingAsUser([]);

        $this->postJson('/api/things', ['name' => 'Test'])->assertForbidden();
    }
}
```

- `type => 'admin'` alone does **not** bypass permissions or tenancy. For a true admin: `App\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])` and `$user->assignRole('admin')`.
- Tenanted data: attach warehouses with `$user->warehouses()->attach($warehouse->id)`.
- Feature flag: `config(['app.enable_document_counter' => true]);`

## Loyalty tests

```php
Sanctum::actingAs($loyaltyUser, ['loyalty'], 'loyalty');   // customer (third arg = guard)
Sanctum::actingAs($staff);                                   // loyalty admin (abilities default ['*'])
```
Loyalty factories live in `database/factories/Loyalty/` with states: `LoyaltyUser::unverified()`, `Claim::forUser()/approved()`, `PointsTransaction::earn()/spend()`, `Prize::inactive()/outOfStock()`, `Redemption::forPrize()/approved()/shipped()`. Loyalty permissions: `Permission::firstOrCreate(['name' => 'review claims', 'guard_name' => 'web'])`.

## Fixtures for products / stock

Most warehouse models don't use `HasFactory`, so call factories directly. Creating a `Warehouse` or `ProductUnit` auto-creates `stock_product_units` via hooks/events — wrap fixtures in `Model::withoutEvents()` when you want full control (see `tests/Feature/Api/Public/StockVerificationTest.php`):

```php
Model::withoutEvents(function () {
    $warehouse = WarehouseFactory::new()->create();
    $productUnit = ProductUnitFactory::new()->create([
        'product_id' => ProductFactory::new()->create([...])->id,
        'uom_id' => UomFactory::new()->create()->id,
    ]);
    $spu = StockProductUnitFactory::new()->create([
        'warehouse_id' => $warehouse->id,
        'product_unit_id' => $productUnit->id,
    ]);
    $stock = StockFactory::new()->create(['stock_product_unit_id' => $spu->id]);
});
```
Alternatively create records with `Model::create([...])` directly (as `NumberingTest` does) and let the hooks run.

## Fakes in use

`Storage::fake('s3')` (brand logos, prizes, payments), `Storage::fake('local')` (claim photos), `Mail::fake()` (loyalty mails are sent synchronously), `Cache::flush()` (reset rate limiters). No HTTP or queue fakes are needed.

## After writing tests

1. Run the specific test, then the related directory.
2. `php8.5 vendor/bin/pint` on the files you changed.
