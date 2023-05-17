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
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\QueryBuilder\QueryBuilder;

class ReceiveOrderDetailController extends Controller
{
    public function index(ReceiveOrder $receiveOrder)
    {
        $receiveOrderDetails = QueryBuilder::for(ReceiveOrderDetail::class)
            // ->allowedFilters('name')
            // ->allowedSorts(['id', 'name', 'created_at'])
            ->paginate();

        return ReceiveOrderDetailResource::collection($receiveOrderDetails);
    }

    public function show(ReceiveOrder $receiveOrder, $receiveOrderDetailId)
    {
        $receiveOrderDetail = $receiveOrder->details()->where('id', $receiveOrderDetailId)->firstOrFail();

        // $qr = QrCode::size(300)
        //     ->format('svg')
        //     ->generate('01h0f8j05z7r0sp42ynm0jf2bs');

        // echo(str_replace("\n", '',$qr));
        // die;

        return new ReceiveOrderDetailResource($receiveOrderDetail);
    }

    public function update(ReceiveOrder $receiveOrder, $receiveOrderDetailId, ReceiveOrderDetailUpdateRequest $request)
    {
        $receiveOrderDetail = $receiveOrder->details()->where('id', $receiveOrderDetailId)->firstOrFail();

        $receiveOrderDetail->update($request->validated());

        return (new ReceiveOrderDetailResource($receiveOrderDetail))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function verify(ReceiveOrder $receiveOrder, $receiveOrderDetailId, Request $request)
    {
        $receiveOrderDetail = $receiveOrder->details()->where('id', $receiveOrderDetailId)->firstOrFail();

        $request->validate([
            'is_verified' => 'required|boolean'
        ]);

        if (is_null($receiveOrderDetail->uom_id)) return response()->json(['message' => 'Data must be verified first'], 400);

        $receiveOrderDetail->is_verified = boolval($request->is_verified);
        if ($receiveOrderDetail->isDirty('is_verified') === false) return response()->json(['message' => 'Unable to update with the same status'], 400);
        $receiveOrderDetail->save();

        return (new ReceiveOrderDetailResource($receiveOrderDetail))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(ReceiveOrder $receiveOrder, ReceiveOrderDetail $receiveOrderDetail)
    {
        $receiveOrderDetail->delete();
        return $this->deletedResponse();
    }
}
