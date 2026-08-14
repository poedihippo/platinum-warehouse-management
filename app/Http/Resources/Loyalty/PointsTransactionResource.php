<?php

namespace App\Http\Resources\Loyalty;

use App\Models\Loyalty\PointsTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PointsTransactionResource extends JsonResource
{
    /**
     * Only claim/redemption sources are linkable — manual adjustments
     * have no target entity (source_id self-references the ledger row).
     */
    private const LINKABLE_SOURCE_TYPES = [
        PointsTransaction::SOURCE_CLAIM,
        PointsTransaction::SOURCE_REDEMPTION,
    ];

    public function toArray(Request $request): array
    {
        $isLinkable = in_array($this->source_type, self::LINKABLE_SOURCE_TYPES, true);

        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'amount' => (int) $this->amount,
            // Signed amount for display convenience.
            'signed_amount' => $this->direction === 'spend'
                ? -1 * (int) $this->amount
                : (int) $this->amount,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'source' => [
                'type' => $isLinkable ? $this->source_type : null,
                'id' => $isLinkable ? $this->source_id : null,
            ],
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
