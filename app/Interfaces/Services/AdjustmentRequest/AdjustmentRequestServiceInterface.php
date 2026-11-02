<?php

namespace App\Interfaces\Services\AdjustmentRequest;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\AdjustmentRequest;

interface AdjustmentRequestServiceInterface extends BaseServiceInterface
{
    public function updateUnlessApproved(AdjustmentRequest $adjustmentRequest, array $data): AdjustmentRequest;

    public function deleteUnlessApproved(AdjustmentRequest $adjustmentRequest): void;

    public function decide(AdjustmentRequest $adjustmentRequest, mixed $reason, mixed $isApproved): AdjustmentRequest;
}
