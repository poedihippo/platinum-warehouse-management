---
name: laravel-controller
description: Use when creating API controllers, adding endpoints/routes, or changing controller logic in this BEJO Laravel API. Covers the Controller → Service → Repository layering, permission middleware, tenancy, index endpoints, Resources, route groups, and loyalty admin authorization.
---

# Laravel API Controller (BEJO)

## Where things go

| Kind | Controller folder | Route group in `routes/api.php` |
|------|-------------------|----------------------------------|
| Warehouse (staff) | `app/Http/Controllers/Api/` | `Route::middleware(['auth:sanctum', 'ability:warehouse'])` |
| Loyalty admin | `app/Http/Controllers/Api/Admin/Loyalty/` | `Route::middleware('auth:sanctum')->prefix('admin/loyalty')` |
| Loyalty customer | `app/Http/Controllers/Api/Loyalty/` | `prefix('loyalty')` + `['auth:loyalty', 'loyalty.active']` |
| Public | `app/Http/Controllers/Api/Public/` | top of file, with `throttle:` |

## Layering (Controller → Service → Repository)

A new module `Thing` needs five classes plus two bindings:

| File | Extends / implements |
|------|----------------------|
| `app/Interfaces/Repositories/Thing/ThingRepositoryInterface.php` | `extends BaseRepositoryInterface` |
| `app/Http/Repositories/Thing/ThingRepository.php` | `extends BaseRepository implements ThingRepositoryInterface` |
| `app/Interfaces/Services/Thing/ThingServiceInterface.php` | `extends BaseServiceInterface` |
| `app/Http/Services/Thing/ThingService.php` | `extends BaseService implements ThingServiceInterface` |
| `app/Http/Controllers/Api/ThingController.php` | `extends BaseController` |

Register both in `AppServiceProvider::$singletons` (`ThingRepositoryInterface::class => ThingRepository::class`, same for the service). `tests/Unit/ContainerBindingsTest` fails if a binding is missing. Model this on the Uom module (simple) or ReceiveOrder (tenanted).

```php
// app/Http/Repositories/Thing/ThingRepository.php
class ThingRepository extends BaseRepository implements ThingRepositoryInterface
{
    public function __construct(Thing $model)
    {
        parent::__construct($model);
    }
}

// app/Http/Services/Thing/ThingService.php
class ThingService extends BaseService implements ThingServiceInterface
{
    public function __construct(protected ThingRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function done(Thing $thing): Thing
    {
        if ($thing->is_done) {
            throw new ApiResponseException('Data sudah selesai', 400);
        }

        return $this->repository->update($thing, ['is_done' => true]);
    }
}
```

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Thing\StoreRequest;
use App\Http\Requests\Api\Thing\UpdateRequest;
use App\Http\Resources\Thing\ThingResource;
use App\Interfaces\Services\Thing\ThingServiceInterface;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;

class ThingController extends BaseController
{
    public function __construct(private ThingServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:thing_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:thing_create', ['only' => 'store']);
        $this->middleware('permission:thing_edit', ['only' => 'update']);
        $this->middleware('permission:thing_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        $things = $this->service->findAllPaginate(
            $this->per_page,
            fn ($q) => $q->tenanted(),
            allowedFilters: ['name', AllowedFilter::exact('warehouse_id'), AllowedFilter::scope('start_date'), AllowedFilter::scope('end_date')],
            allowedIncludes: ['warehouse'],
            allowedSorts: ['id', 'name', 'created_at'],
        );

        return ThingResource::collection($things);
    }

    public function show(int $id)
    {
        return new ThingResource($this->service->findTenanted($id)->load('warehouse'));
    }

    public function store(StoreRequest $request)
    {
        return new ThingResource($this->service->create($request->validated()));
    }

    public function update(int $id, UpdateRequest $request)
    {
        $thing = $this->service->update($this->service->findTenanted($id), $request->validated());

        return (new ThingResource($thing))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(int $id)
    {
        $this->service->delete($this->service->findTenanted($id));

        return $this->deletedResponse();
    }
}
```

Rules:
- Always `parent::__construct()` — `Api\BaseController` sets `$this->per_page` (`?per_page`, default 15, no cap).
- Controllers stay thin: no `QueryBuilder::for`, `DB::`, `Model::create/where`. Queries go in the repository (named method when the chain is non-standard), rules and transactions in the service.
- Services return the model/paginator as-is (no `fresh()` — keeps the implicit 201 from `wasRecentlyCreated`). Business-rule errors: `throw new ApiResponseException($message, $status, $extra)` → `{"message": ..., ...$extra}`. Never `abort()` for these.
- Cross-module needs: inject the other module's **interface** (service or repository). Stock changes → `StockHistoryRepositoryInterface::record($subject, [...])`.
- Permission middleware in the constructor is the **only** authorization layer (no policies, no `$this->authorize()`). For `['only' => ...]` with several methods, pass an **array** — `['only' => 'a', 'b']` silently protects only `a`. Method names must match exactly.
- Warehouse-scoped models (`ReceiveOrder`, `DeliveryOrder`, `StockOpname`, `StockProductUnit`, `Stock`, …): tenancy is explicit — `findTenanted($id)` / a `tenanted()` closure; `BaseRepository::query()` does **not** scope. Non-scoped models (e.g. `Uom`) can use route-model binding.
- Responses: a Resource (`app/Http/Resources/{Module}/`, or `DefaultResource`), `$this->createdResponse()`, `$this->updatedResponse()`, `$this->deletedResponse()`. Messages in Indonesian. Update endpoints commonly return 202.
- Existing endpoints are used by production frontends — never change a response shape or status code while refactoring.

## Permissions

1. Add the group/children to `PermissionsHelper::adminPermissions()` (`app/Helpers/PermissionsHelper.php`) — names use underscores: `thing_access` → `thing_read`, `thing_create`, `thing_edit`, `thing_delete`, plus extras like `thing_done`.
2. If granting it should imply others, extend `getRelatedPermissions()`.
3. Seed: `php artisan db:seed --class=PermissionSeeder` (ask the user before running against their DB).

## Routes

```php
// inside the ['auth:sanctum', 'ability:warehouse'] group
Route::get('things/export', [ThingController::class, 'export']);      // static paths BEFORE apiResource
Route::put('things/{thing}/done', [ThingController::class, 'done']);
Route::group(['prefix' => 'things/{thing}/details'], function () {
    Route::get('/', [ThingDetailController::class, 'index']);
    Route::post('/', [ThingDetailController::class, 'store']);
});
Route::apiResource('things', ThingController::class);
```

## Loyalty admin controllers

No `permission:` middleware and no ability check on the group — each controller authorizes itself:

```php
use App\Traits\Controllers\DeniesUnlessCan;

private const PERMISSION = 'manage prizes';

public function index(IndexRequest $request)
{
    if ($denied = $this->denyUnlessCan($request, self::PERMISSION, 'Anda tidak memiliki izin ...')) {
        return $denied;
    }

    return PrizeResource::collection($this->service->paginateForAdmin($request->validated(), $this->loyaltyPerPage($request)));
}
```
Loyalty permission names are space-separated (`review claims`, `manage prizes`, …) and come from `LoyaltyPermissionSeeder`. Loyalty admin index endpoints build queries by hand (`q`, `sort`, `per_page` capped at 100) rather than QueryBuilder (`ClampsLoyaltyPerPage::loyaltyPerPage()`).

## After creating a controller

1. Form Requests in `app/Http/Requests/Api/Thing/` (`StoreRequest`, `UpdateRequest`; `authorize()` returns true or is omitted; use `RequestToBoolean` + `prepareForValidation()` for flags; `TenantedRule` for warehouse-scoped IDs).
2. Resource in `app/Http/Resources/Thing/`; interface + implementation bindings in `AppServiceProvider::$singletons`.
3. Routes + permissions as above; check with `php artisan route:list --path=api/things`.
4. Feature test (see the `laravel-test` skill), then `./vendor/bin/pint` on the changed files.
