---
name: laravel-controller
description: Use when creating new API controllers, modifying controller logic, or adding new API endpoints in this project. Front-load keywords: create controller, API endpoint, apiResource, Form Request, index store show update destroy, controller pattern.
---

# Laravel API Controller Skill

## Project Conventions

This project is a REST API backend. All controllers live in `app/Http/Controllers/Api/`.

### File Location
- Standard controllers: `app/Http/Controllers/Api/`
- Admin loyalty controllers: `app/Http/Controllers/Api/Admin/Loyalty/`
- Loyalty customer controllers: `app/Http/Controllers/Api/Loyalty/`
- Public controllers: `app/Http/Controllers/Api/Public/`

### Standard Controller Template

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\YourModelStoreRequest;
use App\Http\Requests\Api\YourModelUpdateRequest;
use App\Models\YourModel;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class YourModelController extends Controller
{
    public function index(Request $request)
    {
        $models = QueryBuilder::for(YourModel::class)
            ->allowedFilters([
                AllowedFilter::exact('field'),
                AllowedFilter::partial('name'),
            ])
            ->allowedSorts(['created_at', 'name'])
            ->allowedIncludes([])
            ->tenanted() // Use if model uses Tenanted trait
            ->paginate($request->get('per_page', 15));

        return response()->json($models);
    }

    public function store(YourModelStoreRequest $request)
    {
        $model = YourModel::create($request->validated());

        return response()->json($model, 201);
    }

    public function show(YourModel $yourModel)
    {
        // findTenanted() for tenanted models
        // $yourModel = YourModel::findTenanted($yourModel->id);

        return response()->json($yourModel);
    }

    public function update(YourModelUpdateRequest $request, YourModel $yourModel)
    {
        $yourModel->update($request->validated());

        return response()->json($yourModel);
    }

    public function destroy(YourModel $yourModel)
    {
        $yourModel->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
```

### Key Rules

1. **Always use Form Request** classes for validation:
   - Create in `app/Http/Requests/Api/`
   - Use nested request classes for complex validation
   - Use `RequestToBoolean` trait for boolean conversion

2. **Use Spatie Query Builder** for index endpoints:
   - `allowedFilters()` for filtering
   - `allowedSorts()` for sorting
   - `allowedIncludes()` for eager loading
   - Always chain `tenanted()` for warehouse-scoped models

3. **Use tenanted()** on all queries for warehouse-scoped models:
   ```php
   // In index
   ->tenanted()

   // For single record
   $model = YourModel::findTenanted($id);
   ```

4. **Route Registration** in `routes/api.php`:
   ```php
   // Standard CRUD
   Route::apiResource('your-models', YourModelController::class);

   // Nested resources
   Route::get('parent/{parent}/details', [YourModelController::class, 'index']);
   Route::post('parent/{parent}/details', [YourModelController::class, 'store']);

   // Custom actions
   Route::put('your-models/{yourModel}/approve', [YourModelController::class, 'approve']);
   ```

5. **Response Format**:
   - `index`: Paginated JSON
   - `store`: JSON with 201 status
   - `show`: JSON
   - `update`: JSON
   - `destroy`: JSON with message

6. **Controller Grouping** by middleware in `routes/api.php`:
   - Public: No auth
   - Loyalty: `auth:loyalty`, `loyalty.active`
   - Loyalty Admin: `auth:sanctum` (loyalty ability)
   - Warehouse: `auth:sanctum`, `ability:warehouse`

### Common Patterns

#### Verify Action
```php
public function verify(YourModel $yourModel)
{
    $yourModel->update(['is_verified' => true]);
    return response()->json(['message' => 'Verified successfully']);
}
```

#### Done Action
```php
public function done(YourModel $yourModel)
{
    $yourModel->update(['is_done' => true, 'done_at' => now()]);
    return response()->json(['message' => 'Marked as done']);
}
```

#### Approve Action
```php
public function approve(YourModel $yourModel)
{
    $yourModel->update([
        'is_approved' => true,
        'approved_by' => auth('sanctum')->id(),
        'approved_datetime' => now(),
    ]);
    return response()->json(['message' => 'Approved successfully']);
}
```

### After Creating a Controller

1. Register routes in `routes/api.php`
2. Create Form Request classes in `app/Http/Requests/Api/`
3. Add any necessary permissions to `app/Helpers/PermissionsHelper.php`
4. Test the endpoints with the API
