<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Loyalty\Admin\StorePrizeCategoryRequest;
use App\Http\Requests\Api\Loyalty\Admin\UpdatePrizeCategoryRequest;
use App\Http\Resources\Loyalty\AdminPrizeCategoryResource;
use App\Models\Loyalty\PrizeCategory;
use Illuminate\Http\Request;

class PrizeCategoryManagementController extends Controller
{
    private const PERMISSION = 'manage prizes';

    /**
     * GET /api/admin/loyalty/prize-categories
     * All categories (small set, not paginated) for the management screen.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $categories = PrizeCategory::withCount('prizes')->orderBy('name')->get();

        return AdminPrizeCategoryResource::collection($categories);
    }

    /**
     * POST /api/admin/loyalty/prize-categories
     */
    public function store(StorePrizeCategoryRequest $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $category = PrizeCategory::create($request->validated());

        return (new AdminPrizeCategoryResource($category->loadCount('prizes')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /api/admin/loyalty/prize-categories/{prizeCategory}
     */
    public function update(UpdatePrizeCategoryRequest $request, string $prizeCategory)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = PrizeCategory::find($prizeCategory);
        if (! $model) {
            return response()->json(['message' => 'Kategori hadiah tidak ditemukan.'], 404);
        }

        $model->update($request->validated());

        return new AdminPrizeCategoryResource($model->loadCount('prizes'));
    }

    /**
     * DELETE /api/admin/loyalty/prize-categories/{prizeCategory}
     * Soft delete. Prizes referencing this category are not touched here —
     * the category simply stops resolving via Prize::category() (soft-delete
     * global scope), which the customer-facing side treats as uncategorized.
     */
    public function destroy(Request $request, string $prizeCategory)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = PrizeCategory::find($prizeCategory);
        if (! $model) {
            return response()->json(['message' => 'Kategori hadiah tidak ditemukan.'], 404);
        }

        $model->delete();

        return response()->json(null, 204);
    }

    /**
     * Returns a 403 JSON response when the admin lacks the prize-management
     * permission, else null. Mirrors PrizeManagementController — categories
     * are managed under the same 'manage prizes' permission, not a separate
     * one, since they only exist to organize prizes.
     */
    private function denyUnlessAuthorized(Request $request)
    {
        if ($request->user()?->can(self::PERMISSION)) {
            return null;
        }

        return response()->json([
            'message' => 'Anda tidak memiliki izin untuk mengelola kategori hadiah.',
        ], 403);
    }
}
