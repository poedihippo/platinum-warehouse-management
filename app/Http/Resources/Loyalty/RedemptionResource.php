<?php

namespace App\Http\Resources\Loyalty;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer-facing redemption representation. Nests a minimal prize
 * (name + photo) so a customer can render their redemption history
 * without a second request.
 */
class RedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prize' => $this->when($this->relationLoaded('prize') && $this->prize, fn () => [
                'id' => $this->prize->id,
                'name' => $this->prize->name,
                'photo_url' => $this->prize->photo_url,
            ]),
            'points_spent' => (int) $this->points_spent,
            'quantity' => (int) $this->quantity,
            'status' => $this->status,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'recipient_address' => $this->recipient_address,
            'recipient_notes' => $this->recipient_notes,
            'rejection_reason' => $this->rejection_reason,
            'tracking_number' => $this->tracking_number,
            'shipping_carrier' => $this->shipping_carrier,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
        ];
    }
}
