<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Voucher\StoreRequest;
use App\Http\Resources\DefaultResource;
use App\Models\Voucher;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class VoucherController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:voucher_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:voucher_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:voucher_create', ['only' => 'store']);
        $this->middleware('permission:voucher_edit', ['only' => 'update']);
        $this->middleware('permission:voucher_delete', ['only' => 'destroy']);
    }

    public function index()
    {
        $vouchers = QueryBuilder::for(Voucher::class)
            ->allowedFilters([
                AllowedFilter::exact('voucher_category_id'),
                'code'
            ])
            ->allowedIncludes('voucherCategory')
            ->allowedSorts(['id', 'voucher_category_id', 'name', 'code', 'created_at'])
            ->paginate($this->per_page);

        return DefaultResource::collection($vouchers);
    }

    public function show(Voucher $voucher)
    {
        return new DefaultResource($voucher->load('voucherCategory'));
    }

    public function store(StoreRequest $request)
    {
        $voucher = Voucher::create($request->validated());

        return new DefaultResource($voucher);
    }

    public function update(Voucher $voucher, StoreRequest $request)
    {
        $voucher->update($request->validated());

        return (new DefaultResource($voucher))->response()->setStatusCode(\Illuminate\Http\Response::HTTP_ACCEPTED);
    }

    public function destroy(Voucher $voucher)
    {
        $voucher->delete();
        return $this->deletedResponse();
    }

    public function forceDelete(int $id)
    {
        $voucher = Voucher::withTrashed()->findOrFail($id);
        $voucher->forceDelete();

        return $this->deletedResponse();
    }

    public function restore(int $id)
    {
        $voucher = Voucher::withTrashed()->findOrFail($id);
        $voucher->restore();

        return new DefaultResource($voucher);
    }
}
