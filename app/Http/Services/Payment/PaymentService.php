<?php

namespace App\Http\Services\Payment;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Payment\PaymentRepositoryInterface;
use App\Interfaces\Repositories\SalesOrder\SalesOrderRepositoryInterface;
use App\Interfaces\Services\Payment\PaymentServiceInterface;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentService extends BaseService implements PaymentServiceInterface
{
    public function __construct(
        protected PaymentRepositoryInterface $repository,
        protected SalesOrderRepositoryInterface $salesOrderRepository,
    ) {
        parent::__construct($repository);
    }

    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateIndex($perPage);
    }

    /**
     * A full payment takes its amount from the sales order price.
     */
    public function store(array $data, mixed $isFullPayment, mixed $salesOrderId, ?array $files): Payment
    {
        DB::beginTransaction();
        try {
            if ($isFullPayment == true) {
                $salesOrder = $this->salesOrderRepository->query()->where('id', $salesOrderId)->firstOrFail(['price']);
                $data['amount'] = $salesOrder->price;
            }

            $payment = $this->repository->create($data);
            $this->attachFiles($payment, $files);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            throw new ApiResponseException($e->getMessage(), 500);
        }

        return $payment;
    }

    public function updatePayment(Payment $payment, array $data, mixed $isFullPayment, mixed $salesOrderId, ?array $files): Payment
    {
        DB::beginTransaction();
        try {
            if ($isFullPayment == true) {
                $salesOrder = $this->salesOrderRepository->query()->where('id', $salesOrderId)->firstOrFail(['price']);
                $data['amount'] = $salesOrder->price;
            }

            $payment->update($data);
            $this->attachFiles($payment, $files);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            throw new ApiResponseException($e->getMessage(), 500);
        }

        return $payment;
    }

    public function findTenantedWithTrashed(int|string $id): Payment
    {
        return Payment::withTrashed()->findTenanted($id);
    }

    private function attachFiles(Payment $payment, ?array $files): void
    {
        if ($files) {
            foreach ($files as $file) {
                if ($file->isValid()) {
                    $payment->addMedia($file)->toMediaCollection('payments');
                }
            }
        }
    }
}
