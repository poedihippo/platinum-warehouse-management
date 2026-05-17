<?php

namespace App\Http\Resources\Loyalty;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Admin/bejo view of a claim. Includes customer info, signed photo
 * URLs, line items (with product names + current points value) and the
 * cross-user duplicate-invoice soft warnings (spec §9.1 / Section 7).
 */
class AdminClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->whenLoaded('loyaltyUser');

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'total_points' => (int) $this->total_points,
            'rejection_reason' => $this->rejection_reason,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reviewed_by' => $this->reviewed_by,
            'created_at' => $this->created_at?->toIso8601String(),

            'customer' => $this->when(
                $this->relationLoaded('loyaltyUser') && $customer,
                fn () => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ]
            ),

            'invoice_photo_url' => $this->invoice_photo_path
                ? self::signedUrl($this->invoice_photo_path)
                : null,

            'photos' => $this->whenLoaded('photos', fn () => $this->photos
                ->sortBy('position')
                ->map(fn ($photo) => [
                    'id' => $photo->id,
                    'position' => $photo->position,
                    'url' => self::signedUrl($photo->photo_path),
                ])
                ->values()),

            'line_items' => $this->whenLoaded('lineItems', fn () => $this->lineItems
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'product_unit_id' => $item->product_unit_id,
                    'product_unit_name' => $item->productUnit?->name,
                    'points_per_unit' => (int) ($item->productUnit?->points_per_unit ?? 0),
                    'quantity' => (int) $item->quantity,
                    'points_awarded' => (int) $item->points_awarded,
                ])
                ->values()),

            // Section 7 — cross-user duplicate invoice soft warnings.
            // Attached by the controller; never auto-rejects.
            'duplicate_warnings' => $this->duplicate_warnings ?? [],
        ];
    }

    /**
     * Prefer a time-limited signed URL (S3). Fall back to a plain URL
     * for the local disk, which does not support temporaryUrl.
     */
    private static function signedUrl(string $path): ?string
    {
        try {
            return Storage::temporaryUrl($path, now()->addMinutes(15));
        } catch (\Throwable $e) {
            return Storage::url($path);
        }
    }
}
