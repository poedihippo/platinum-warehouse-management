<?php

namespace App\Http\Resources\Loyalty;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Customer-facing claim representation. Explicit allow-list — never
 * returns the raw model. Photos / line items only when eager-loaded
 * (detail endpoint), not on the list endpoint.
 */
class ClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_photo_url' => $this->invoice_photo_path
                ? Storage::url($this->invoice_photo_path)
                : null,
            'status' => $this->status,
            'total_points' => (int) $this->total_points,
            'rejection_reason' => $this->rejection_reason,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos
                ->sortBy('position')
                ->map(fn ($photo) => [
                    'id' => $photo->id,
                    'position' => $photo->position,
                    'url' => Storage::url($photo->photo_path),
                ])
                ->values()),
            'line_items' => $this->whenLoaded('lineItems', fn () => $this->lineItems
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'product_unit_id' => $item->product_unit_id,
                    'quantity' => (int) $item->quantity,
                    'points_awarded' => (int) $item->points_awarded,
                ])
                ->values()),
        ];
    }
}
