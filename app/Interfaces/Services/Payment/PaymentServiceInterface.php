<?php

namespace App\Interfaces\Services\Payment;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentServiceInterface extends BaseServiceInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function store(array $data, mixed $isFullPayment, mixed $salesOrderId, ?array $files): Payment;

    public function updatePayment(Payment $payment, array $data, mixed $isFullPayment, mixed $salesOrderId, ?array $files): Payment;

    public function findTenantedWithTrashed(int|string $id): Payment;
}
