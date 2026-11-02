<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\VoucherCategory\StoreRequest;
use App\Http\Resources\DefaultResource;
use App\Interfaces\Services\VoucherCategory\VoucherCategoryServiceInterface;
use App\Models\VoucherCategory;
use Illuminate\Http\Response;

class VoucherCategoryController extends BaseController
{
    public function __construct(private VoucherCategoryServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:voucher_create', ['only' => 'store']);
        $this->middleware('permission:voucher_edit', ['only' => 'update']);
        $this->middleware('permission:voucher_delete', ['only' => 'destroy', 'forceDelete', 'restore']);
    }

    public function index()
    {
        $voucherCategorys = $this->service->findAllPaginate(
            $this->per_page,
            null,
            ['name', 'discount_type'],
            [],
            ['id', 'name', 'discount_type', 'created_at'],
        );

        return DefaultResource::collection($voucherCategorys);
    }

    public function show(VoucherCategory $voucherCategory)
    {
        return new DefaultResource($voucherCategory);
    }

    public function store(StoreRequest $request)
    {
        return new DefaultResource($this->service->create($request->validated()));
    }

    public function update(VoucherCategory $voucherCategory, StoreRequest $request)
    {
        $this->service->update($voucherCategory, $request->validated());

        return (new DefaultResource($voucherCategory))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(VoucherCategory $voucherCategory)
    {
        $this->service->delete($voucherCategory);

        return $this->deletedResponse();
    }

    public function forceDelete($id)
    {
        $this->service->forceDelete($this->service->findWithTrashedOrFail($id));

        return $this->deletedResponse();
    }

    public function restore($id)
    {
        $voucherCategory = $this->service->findWithTrashedOrFail($id);
        $this->service->restore($voucherCategory);

        return new DefaultResource($voucherCategory);
    }
}
