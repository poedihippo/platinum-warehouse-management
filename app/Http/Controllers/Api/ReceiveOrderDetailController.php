<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReceiveOrderDetailStoreRequest;
use App\Http\Requests\Api\ReceiveOrderDetailUpdateRequest;
use App\Http\Resources\ReceiveOrderDetailResource;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;

class ReceiveOrderDetailController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('permission:receive_order_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:receive_order_create', ['only' => 'store']);
        $this->middleware('permission:receive_order_edit', ['only' => 'update']);
        $this->middleware('permission:receive_order_delete', ['only' => 'destroy']);
        $this->middleware('permission:receive_order_verify_access', ['only' => 'verify']);
    }

    public function index(int $receiveOrderId)
    {
        // abort_if(!auth()->user()->tokenCan('receive_order_access'), 403);
        $receiveOrder = ReceiveOrder::findTenanted($receiveOrderId, ['id']);

        $receiveOrderDetails = QueryBuilder::for(ReceiveOrderDetail::where('receive_order_id', $receiveOrder->id))
            ->allowedFilters([
                AllowedFilter::scope('product_unit'),
                AllowedFilter::callback('product_brand_id', function (Builder $query, $value) {
                    $query->whereHas('productUnit.product', fn ($q) => $q->where('product_brand_id', $value));
                }),
                AllowedFilter::callback('product_category_id', function (Builder $query, $value) {
                    $query->whereHas('productUnit.product', fn ($q) => $q->where('product_category_id', $value));
                }),
            ])
            // ->allowedSorts(['id', 'invoice_no', 'user_id', 'supplier_id', 'warehouse_id', 'created_at'])
            ->paginate($this->per_page);

        return ReceiveOrderDetailResource::collection($receiveOrderDetails);
    }

    public function show(int $receiveOrderId, int $receiveOrderDetailId)
    {
        // abort_if(!auth()->user()->tokenCan('receive_order_access'), 403);

        $receiveOrder = ReceiveOrder::findTenanted($receiveOrderId, ['id']);
        $receiveOrderDetail = $receiveOrder->details()->where('id', $receiveOrderDetailId)->firstOrFail();

        return new ReceiveOrderDetailResource($receiveOrderDetail);
    }

    /**
     * manual create RO detail
     */
    public function store(int $receiveOrderId, ReceiveOrderDetailStoreRequest $request)
    {
        $receiveOrder = ReceiveOrder::findTenanted($receiveOrderId, ['id']);
        $receiveOrderDetail = $receiveOrder->details()->create($request->validated());

        return new ReceiveOrderDetailResource($receiveOrderDetail);
    }

    /**
     * adjust qty RO detail
     */
    public function update(int $receiveOrderId, $receiveOrderDetailId, ReceiveOrderDetailUpdateRequest $request)
    {
        $receiveOrder = ReceiveOrder::findTenanted($receiveOrderId, ['id']);
        $receiveOrderDetail = $receiveOrder->details()->where('id', $receiveOrderDetailId)->firstOrFail();

        $receiveOrderDetail->update($request->validated());

        return (new ReceiveOrderDetailResource($receiveOrderDetail))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    /**
     * verify RO detail and insert to stocks
     */
    public function verify(int $receiveOrderId, $receiveOrderDetailId, Request $request)
    {
        // abort_if(!auth()->user()->tokenCan('receive_order_verify_access'), 403);

        $receiveOrder = ReceiveOrder::findTenanted($receiveOrderId, ['id']);
        if ($receiveOrder->is_done) return response()->json(['message' => "Delivery order sudah diverifikasi. Tidak dapat mengubah detail"], 400);

        $receiveOrderDetail = $receiveOrder->details()->where('id', $receiveOrderDetailId)->firstOrFail();

        $request->validate([
            'is_verified' => 'required|boolean'
        ]);

        $receiveOrderDetail->is_verified = boolval($request->is_verified);
        if ($receiveOrderDetail->isDirty('is_verified') === false) return response()->json(['message' => 'Tidak dapat memperbarui dengan status yang sama'], 400);
        $receiveOrderDetail->save();

        return (new ReceiveOrderDetailResource($receiveOrderDetail))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(int $receiveOrderId, ReceiveOrderDetail $receiveOrderDetail)
    {
        // abort_if(!auth()->user()->tokenCan('receive_order_delete'), 403);

        $receiveOrder = ReceiveOrder::findTenanted($receiveOrderId, ['id']);
        if ($receiveOrderDetail->is_verified === true) return response()->json(['message' => 'Data harus tidak diverifikasi']);

        DB::beginTransaction();
        try {
            $receiveOrderDetail->delete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json($th, 500);
        }

        return $this->deletedResponse();
    }
}
