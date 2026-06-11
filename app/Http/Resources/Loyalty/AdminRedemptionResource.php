<?php

namespace App\Http\Resources\Loyalty;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin/bejo view of a redemption: the full shipping payload plus a
 * minimal customer block and the prize (name + photo) for the queue.
 */
class AdminRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
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
            'reviewed_by' => $this->reviewed_by,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'customer' => $this->when($this->relationLoaded('loyaltyUser') && $this->loyaltyUser, fn () => [
                'id' => $this->loyaltyUser->id,
                'name' => $this->loyaltyUser->name,
                'email' => $this->loyaltyUser->email,
                'phone' => $this->loyaltyUser->phone,
            ]),

            'prize' => $this->when($this->relationLoaded('prize') && $this->prize, fn () => [
                'id' => $this->prize->id,
                'name' => $this->prize->name,
                'photo_url' => $this->prize->photo_url,
            ]),
        ];
    }
}
