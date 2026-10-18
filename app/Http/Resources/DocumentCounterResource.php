<?php

namespace App\Http\Resources;

use App\Services\NumberingService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class DocumentCounterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * `sequence` is the raw column: the last number that has been used.
     * `next_sequence` is the value the frontend shows and edits: the number
     * the next document of this scope will receive.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company' => $this->company->value,
            'document_type' => $this->document_type->value,
            'period' => $this->period,
            'sequence' => $this->sequence,
            'next_sequence' => $this->nextSequence(),
            'next_number' => $this->nextNumber(),
            'is_persisted' => $this->exists,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The number the next document of this scope will be given.
     */
    public function nextSequence(): int
    {
        return $this->sequence + 1;
    }

    /**
     * Rendered preview of the next document number, e.g. "PAS/SO/10/26/01".
     */
    public function nextNumber(): string
    {
        return NumberingService::format(
            $this->resource->company,
            $this->resource->document_type,
            Carbon::parse($this->period.'-01'),
            $this->nextSequence(),
        );
    }
}
