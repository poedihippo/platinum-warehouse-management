<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductUnitResource;
use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductUnitPointsController extends Controller
{
    /**
     * PATCH /api/admin/product-units/{productUnit}/points
     *
     * Inline-edit a product unit's loyalty points_per_unit value.
     */
    public function update(Request $request, ProductUnit $productUnit)
    {
        if (! $request->user()->can('manage loyalty points')) {
            return response()->json([
                'message' => 'You are not authorized to manage loyalty points.',
            ], 403);
        }

        $validated = $request->validate([
            'points_per_unit' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        Log::info('Product unit points updated', [
            'product_unit_id' => $productUnit->id,
            'old_value' => $productUnit->getOriginal('points_per_unit'),
            'new_value' => $validated['points_per_unit'],
            'changed_by' => auth()->id(),
        ]);

        // points_per_unit is intentionally absent from ProductUnit::$fillable.
        $productUnit->points_per_unit = $validated['points_per_unit'];
        $productUnit->save();

        return new ProductUnitResource($productUnit);
    }
}
