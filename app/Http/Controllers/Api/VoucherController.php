<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Voucher\StoreRequest;
use App\Http\Resources\DefaultResource;
use App\Interfaces\Services\Voucher\VoucherServiceInterface;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends BaseController
{
    public function __construct(private VoucherServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:voucher_create', ['only' => 'store']);
        $this->middleware('permission:voucher_edit', ['only' => 'update']);
        $this->middleware('permission:voucher_delete', ['only' => ['destroy', 'forceDelete', 'restore']]);
        $this->middleware('permission:voucher_import', ['only' => 'import']);
    }

    public function index()
    {
        return DefaultResource::collection($this->service->paginateIndex($this->per_page));
    }

    public function show(Voucher $voucher)
    {
        return new DefaultResource($voucher->load([
            'category',
            'voucherGenerateBatch',
            'salesOrders' => fn ($q) => $q->select('id', 'invoice_no'),
        ]));
    }

    public function store(StoreRequest $request)
    {
        $this->service->createVoucher($request->validated());

        return $this->createdResponse();
    }

    public function update(Voucher $voucher, StoreRequest $request)
    {
        $this->service->updateVoucher($voucher, $request->validated());

        return $this->updatedResponse();
    }

    public function destroy(Voucher $voucher)
    {
        $this->service->deleteUnlessUsed($voucher);

        return $this->deletedResponse();
    }

    public function forceDelete($id)
    {
        $this->service->forceDeleteUnlessUsed($id);

        return $this->deletedResponse();
    }

    public function restore($id)
    {
        $this->service->restoreById($id);

        return $this->updatedResponse();
    }

    public function import(Request $request)
    {
        $request->validate([
            'voucher_category_id' => 'required|exists:voucher_categories,id',
            'description' => 'nullable|string',
            'file' => 'required|mimes:xls,xlsx,csv',
        ]);

        $totalInserted = $this->service->import($request->voucher_category_id, $request->description, $request->file('file'));

        return $this->createdResponse($totalInserted.' data inserted successfully');
    }
}
