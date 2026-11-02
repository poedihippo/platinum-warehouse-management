---
name: laravel-controller
description: Use when creating API controllers, adding endpoints/routes, or changing controller logic in this BEJO Laravel API. Covers permission middleware, tenancy, QueryBuilder index endpoints, Resources, route groups, and loyalty admin authorization.
---

# Laravel API Controller (BEJO)

## Where things go

| Kind | Controller folder | Route group in `routes/api.php` |
|------|-------------------|----------------------------------|
| Warehouse (staff) | `app/Http/Controllers/Api/` | `Route::middleware(['auth:sanctum', 'ability:warehouse'])` |
| Loyalty admin | `app/Http/Controllers/Api/Admin/Loyalty/` | `Route::middleware('auth:sanctum')->prefix('admin/loyalty')` |
| Loyalty customer | `app/Http/Controllers/Api/Loyalty/` | `prefix('loyalty')` + `['auth:loyalty', 'loyalty.active']` |
| Public | `app/Http/Controllers/Api/Public/` | top of file, with `throttle:` |

## Warehouse controller template

Model this on `UomController` (simple) or `ReceiveOrderController` (tenanted).

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ThingStoreRequest;
use App\Http\Requests\Api\ThingUpdateRequest;
use App\Http\Resources\ThingResource;
use App\Models\Thing;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ThingController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission:thing_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:thing_create', ['only' => 'store']);
        $this->middleware('permission:thing_edit', ['only' => 'update']);
        $this->middleware('permission:thing_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        $things = QueryBuilder::for(Thing::tenanted())
            ->allowedFilters([
                'name',
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
            ])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->allowedIncludes(['warehouse'])
            ->paginate($this->per_page);

        return ThingResource::collection($things);
    }

    public function show(int $id)
    {
        $thing = Thing::findTenanted($id);

        return new ThingResource($thing->load('warehouse'));
    }

    public function store(ThingStoreRequest $request)
    {
        $thing = Thing::create($request->validated());

        return new ThingResource($thing);
    }

    public function update(int $id, ThingUpdateRequest $request)
    {
        $thing = Thing::findTenanted($id);
        $thing->update($request->validated());

        return (new ThingResource($thing))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(int $id)
    {
        $thing = Thing::findTenanted($id);
        $thing->delete();

        return $this->deletedResponse();
    }
}
```

Rules:
- Always `parent::__construct()` — it sets `$this->per_page` (`?per_page`, default 15).
- Permission middleware in the constructor is the **only** authorization layer (no policies, no `$this->authorize()`). For `['only' => ...]` with several methods, pass an **array** — `['only' => 'a', 'b']` silently protects only `a`. Method names must match exactly.
- Warehouse-scoped models (`ReceiveOrder`, `DeliveryOrder`, `StockOpname`, `StockProductUnit`, `Stock`, …): use `Model::tenanted()` in queries and `Model::findTenanted($id)` instead of route-model binding. Non-scoped models (e.g. `Uom`) can use route-model binding.
- Responses: a Resource (`app/Http/Resources/`, or `DefaultResource` for passthrough), `$this->createdResponse()`, `$this->updatedResponse()`, `$this->deletedResponse()`, or `response()->json(['message' => '...'], 400)` for business-rule errors. Messages in Indonesian.
- Update endpoints commonly return 202.
- Multi-step writes go in `DB::transaction(...)`. Sales-order logic belongs in `SalesOrderService` / pipes, not the controller.

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
private const PERMISSION = 'manage prizes';

public function index(Request $request)
{
    if ($denied = $this->denyUnlessAuthorized($request)) {
        return $denied;
    }
    // ...
}

private function denyUnlessAuthorized(Request $request)
{
    if ($request->user()?->can(self::PERMISSION)) {
        return null;
    }

    return response()->json(['message' => 'Anda tidak memiliki izin ...'], 403);
}
```
Loyalty permission names are space-separated (`review claims`, `manage prizes`, …) and come from `LoyaltyPermissionSeeder`. Loyalty admin index endpoints build queries by hand (`q`, `sort`, `per_page` capped at 100) rather than QueryBuilder.

## After creating a controller

1. Form Requests in `app/Http/Requests/Api/` (`ThingStoreRequest`, `ThingUpdateRequest`; `authorize()` returns true or is omitted; use `RequestToBoolean` + `prepareForValidation()` for flags; `TenantedRule` for warehouse-scoped IDs).
2. Resource in `app/Http/Resources/`.
3. Routes + permissions as above; check with `php artisan route:list --path=api/things`.
4. Feature test (see the `laravel-test` skill), then `./vendor/bin/pint` on the changed files.
