<?php

namespace App\Interfaces\Services\ReceiveOrder;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\ReceiveOrder;

interface ReceiveOrderServiceInterface extends BaseServiceInterface
{
    public function createFromXml(mixed $file, mixed $receiveDatetime): ReceiveOrder;

    public function deleteIfUnverified(ReceiveOrder $receiveOrder): void;

    public function setDone(ReceiveOrder $receiveOrder, mixed $isDone): ReceiveOrder;
}
