<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdjustmentRequestStoreRequest;
use App\Http\Resources\AdjustmentRequestResource;
use App\Models\AdjustmentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AdjustmentRequestController extends Controller
{
    public function index(Request $request)
    {
        abort_if(!auth()->user()->tokenCan('adjustment_requests_access'), 403);

        $adjustmentRequests = QueryBuilder::for(AdjustmentRequest::with(['user', 'stockProductUnit']))
            ->allowedFilters([
                'user_id', 'stock_product_unit_id',
                AllowedFilter::scope('startDate'),
                AllowedFilter::scope('endDate'),
            ])
            ->allowedSorts(['id', 'user_id', 'stock_product_unit_id', 'created_at'])
            ->paginate();

        return AdjustmentRequestResource::collection($adjustmentRequests);
    }

    public function show(AdjustmentRequest $adjustmentRequest)
    {
        abort_if(!auth()->user()->tokenCan('AdjustmentRequest_create'), 403);
        return new AdjustmentRequestResource($adjustmentRequest);
    }

    public function store(AdjustmentRequestStoreRequest $request)
    {
        $adjustmentRequest = AdjustmentRequest::create($request->validated());

        return new AdjustmentRequestResource($adjustmentRequest->load('stockProductUnit'));
    }

    public function update(AdjustmentRequest $adjustmentRequest, AdjustmentRequestStoreRequest $request)
    {
        if ($adjustmentRequest->is_approved) return response()->json(['message' => "Can't update data if it has been approved"], 400);
        $adjustmentRequest->update($request->validated());

        return (new AdjustmentRequestResource($adjustmentRequest->load('stockProductUnit')))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(AdjustmentRequest $adjustmentRequest)
    {
        abort_if(!auth()->user()->tokenCan('AdjustmentRequest_delete'), 403);
        $adjustmentRequest->delete();
        return $this->deletedResponse();
    }
}
