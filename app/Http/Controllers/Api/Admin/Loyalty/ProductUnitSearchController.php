<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Resources\Loyalty\AdminProductUnitSearchResource;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductUnitSearchController extends Controller
{
    private const PERMISSION = 'review claims';

    /**
     * GET /api/admin/loyalty/product-units?q=...
     *
     * Autocomplete source for claim line-item entry. Only units with a
     * positive points_per_unit are returned — a unit that earns no points
     * can't be a claim line item (see ClaimReviewController::addLineItem).
     * Gated with the same permission as the claims routes it feeds.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $query = ProductUnit::with('product:id,name')
            ->where('points_per_unit', '>', 0);

        if ($request->filled('q')) {
            $this->applyTokenSearch($query, $request->input('q'));
        }

        $units = $query->orderBy('name')->limit(20)->get();

        return AdminProductUnitSearchResource::collection($units);
    }

    /**
     * Matches the way admins actually read a product: the UI shows
     * "{product.name} - {unit.name}", a string that exists in no single
     * column, so a plain LIKE on the unit alone misses it.
     *
     * Every whitespace-separated token must match somewhere in
     * (product.name OR unit.name OR unit.code) — an AND of ORs. That makes
     * "CZ Aqua - CZ Bacta Extrem", "Bacta Extrem" and "CZM014" all land on
     * the same row without special-casing the joined display string.
     *
     * Not ProductUnit::scopeSearch, which the points screen also uses.
     */
    private function applyTokenSearch(Builder $query, string $term): void
    {
        $tokens = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            // The separator in "CZ Aqua - CZ Bacta Extrem" is its own token
            // and matches nothing, which would AND the whole result to empty.
            if (!preg_match('/[\p{L}\p{N}]/u', $token)) {
                continue;
            }

            $like = '%' . $token . '%';

            $query->where(fn (Builder $q) => $q
                ->where('product_units.name', 'like', $like)
                ->orWhere('product_units.code', 'like', $like)
                ->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', $like)));
        }
    }

    /**
     * Returns a 403 JSON response when the admin lacks the claims-review
     * permission, else null. Mirrors ClaimReviewController.
     */
    private function denyUnlessAuthorized(Request $request)
    {
        if ($request->user()?->can(self::PERMISSION)) {
            return null;
        }

        return response()->json([
            'message' => 'Anda tidak memiliki izin untuk meninjau klaim.',
        ], 403);
    }
}
