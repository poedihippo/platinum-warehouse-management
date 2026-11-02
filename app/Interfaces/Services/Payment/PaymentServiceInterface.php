<?php

namespace App\Interfaces\Services\Payment;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentServiceInterface extends BaseServiceInterface
{
    public function paginateIndex(int $perPage): LengthAwarePaginator;

    public function store(mixed $data, mixed $isFullPayment, mixed $salesOrderId, mixed $files): Payment;

    public function updatePayment(Payment $payment, mixed $data, mixed $isFullPayment, mixed $salesOrderId, mixed $files): Payment;

    public function findTenantedWithTrashed(mixed $id): Payment;
}
