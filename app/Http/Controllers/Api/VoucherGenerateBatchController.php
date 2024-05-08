<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\VoucherGenerateBatch\StoreRequest;
use App\Http\Resources\DefaultResource;
use App\Models\VoucherGenerateBatch;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class VoucherGenerateBatchController extends Controller
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
        $voucherGenerateBatchs = QueryBuilder::for(VoucherGenerateBatch::class)
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
            ])
            ->allowedIncludes('user')
            ->allowedSorts(['id', 'user_id', 'created_at'])
            ->paginate($this->per_page);

        return DefaultResource::collection($voucherGenerateBatchs);
    }

    public function show(VoucherGenerateBatch $generateBatch)
    {
        return new DefaultResource($generateBatch->load('user')->loadCount('vouchers'));
    }

    public function store(StoreRequest $request)
    {
        DB::beginTransaction();
        try {
            $voucherGenerateBatch = VoucherGenerateBatch::create($request->validated());
            for ($i = 0; $i < $request->value; $i++) {
                $voucherGenerateBatch->vouchers()->create([
                    'voucher_category_id' => $request->voucher_category_id,
                    'code' => $voucherGenerateBatch->id . '-' . $i
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage());
        }

        return new DefaultResource($voucherGenerateBatch);
    }

    public function update(VoucherGenerateBatch $generateBatch, StoreRequest $request)
    {
        $generateBatch->update($request->validated());

        return (new DefaultResource($generateBatch))->response()->setStatusCode(\Illuminate\Http\Response::HTTP_ACCEPTED);
    }

    public function destroy(VoucherGenerateBatch $generateBatch)
    {
        $generateBatch->delete();
        return $this->deletedResponse();
    }

    // public function forceDelete(int $id)
    // {
    //     $voucherGenerateBatch = VoucherGenerateBatch::withTrashed()->findOrFail($id);
    //     $voucherGenerateBatch->forceDelete();

    //     return $this->deletedResponse();
    // }

    // public function restore(int $id)
    // {
    //     $voucherGenerateBatch = VoucherGenerateBatch::withTrashed()->findOrFail($id);
    //     $voucherGenerateBatch->restore();

    //     return new DefaultResource($voucherGenerateBatch);
    // }
}
