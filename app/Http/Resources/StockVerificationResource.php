<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class StockVerificationResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'serial_number' => $this->id,
            'product_name'  => $this->stockProductUnit?->productUnit?->product?->name,
            'expired_date'  => $this->expired_date
                ? Carbon::parse($this->expired_date)->format('Y-m-d')
                : null,
        ];
    }
}
