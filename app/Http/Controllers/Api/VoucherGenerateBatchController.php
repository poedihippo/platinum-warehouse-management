<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\VoucherGenerateBatch\StoreRequest;
use App\Http\Resources\DefaultResource;
use App\Interfaces\Services\VoucherGenerateBatch\VoucherGenerateBatchServiceInterface;
use App\Models\VoucherGenerateBatch;

class VoucherGenerateBatchController extends BaseController
{
    public function __construct(private VoucherGenerateBatchServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:voucher_create', ['only' => 'store']);
        $this->middleware('permission:voucher_edit', ['only' => 'update']);
        $this->middleware('permission:voucher_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        return DefaultResource::collection($this->service->paginateIndex($this->per_page));
    }

    public function show(VoucherGenerateBatch $generateBatch)
    {
        return new DefaultResource($generateBatch->load('user')->loadCount('vouchers'));
    }

    public function store(StoreRequest $request)
    {
        $this->service->generate(
            $request->validated(),
            $request->value,
            $request->voucher_category_id,
            $request->start_date,
            $request->end_date,
        );

        return $this->createdResponse();
    }

    public function update(VoucherGenerateBatch $generateBatch, StoreRequest $request)
    {
        $this->service->update($generateBatch, $request->validated());

        return $this->updatedResponse();
    }

    public function destroy(VoucherGenerateBatch $generateBatch)
    {
        $this->service->delete($generateBatch);

        return $this->deletedResponse();
    }
}
