<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockVerificationResource;
use App\Models\Stock;

class StockVerificationController extends Controller
{
    // Crockford base32 alphabet (no I, L, O, U), 26 chars. Case-insensitive.
    private const ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    public function show(string $ulid)
    {
        if (!preg_match(self::ULID_PATTERN, $ulid)) {
            return $this->notFound();
        }

        $stock = Stock::with([
            'stockProductUnit:id,product_unit_id',
            'stockProductUnit.productUnit:id,product_id',
            'stockProductUnit.productUnit.product:id,name',
        ])
            ->select(['id', 'stock_product_unit_id', 'expired_date'])
            ->find($ulid);

        if (!$stock || !$stock->stockProductUnit?->productUnit?->product) {
            return $this->notFound();
        }

        return response()->json([
            'verified' => true,
            'data'     => new StockVerificationResource($stock),
        ]);
    }

    private function notFound()
    {
        return response()->json([
            'verified' => false,
            'message'  => 'Product not found',
        ], 404);
    }
}
