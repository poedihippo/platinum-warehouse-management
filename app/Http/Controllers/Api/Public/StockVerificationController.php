<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\StockVerificationResource;
use App\Interfaces\Services\Stock\StockServiceInterface;

class StockVerificationController extends BaseController
{
    // Crockford base32 alphabet (no I, L, O, U), 26 chars. Case-insensitive.
    private const ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    public function __construct(private StockServiceInterface $service)
    {
        parent::__construct();
    }

    public function show(string $ulid)
    {
        if (! preg_match(self::ULID_PATTERN, $ulid)) {
            return $this->notFound();
        }

        $stock = $this->service->findForPublicVerification($ulid);

        $product = $stock?->stockProductUnit?->productUnit?->product;
        if (! $stock || ! $product || ! $product->productBrand) {
            return $this->notFound();
        }

        return response()->json([
            'verified' => true,
            'data' => new StockVerificationResource($stock),
        ]);
    }

    private function notFound()
    {
        return response()->json([
            'verified' => false,
            'message' => 'Product not found',
        ], 404);
    }
}
