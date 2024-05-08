<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\VoucherCategory\StoreRequest;
use App\Http\Resources\DefaultResource;
use App\Models\VoucherCategory;
use Spatie\QueryBuilder\QueryBuilder;

class VoucherCategoryController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:voucher_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:voucher_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:voucher_create', ['only' => 'store']);
        $this->middleware('permission:voucher_edit', ['only' => 'update']);
        $this->middleware('permission:voucher_delete', ['only' => 'destroy', 'forceDelete', 'restore']);
    }

    public function index()
    {
        $voucherCategorys = QueryBuilder::for(VoucherCategory::class)
            ->allowedFilters([
                'name', 'discount_type'
            ])
            ->allowedSorts(['id', 'name', 'discount_type', 'created_at'])
            ->paginate($this->per_page);

        return DefaultResource::collection($voucherCategorys);
    }

    public function show(VoucherCategory $voucherCategory)
    {
        return new DefaultResource($voucherCategory);
    }

    public function store(StoreRequest $request)
    {
        $voucherCategory = VoucherCategory::create($request->validated());

        return new DefaultResource($voucherCategory);
    }

    public function update(VoucherCategory $voucherCategory, StoreRequest $request)
    {
        $voucherCategory->update($request->validated());

        return (new DefaultResource($voucherCategory))->response()->setStatusCode(\Illuminate\Http\Response::HTTP_ACCEPTED);
    }

    public function destroy(VoucherCategory $voucherCategory)
    {
        $voucherCategory->delete();
        return $this->deletedResponse();
    }

    public function forceDelete(int $id)
    {
        $voucherCategory = VoucherCategory::withTrashed()->findOrFail($id);
        $voucherCategory->forceDelete();

        return $this->deletedResponse();
    }

    public function restore(int $id)
    {
        $voucherCategory = VoucherCategory::withTrashed()->findOrFail($id);
        $voucherCategory->restore();

        return new DefaultResource($voucherCategory);
    }
}
