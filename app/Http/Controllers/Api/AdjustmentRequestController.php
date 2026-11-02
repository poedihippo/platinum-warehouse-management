<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AdjustmentRequest\StoreRequest;
use App\Http\Resources\AdjustmentRequest\AdjustmentRequestResource;
use App\Interfaces\Services\AdjustmentRequest\AdjustmentRequestServiceInterface;
use App\Models\AdjustmentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;

class AdjustmentRequestController extends BaseController
{
    public function __construct(private AdjustmentRequestServiceInterface $service)
    {
        parent::__construct();
        // $this->middleware('permission:adjustment_request_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:adjustment_request_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:adjustment_request_create', ['only' => 'store']);
        $this->middleware('permission:adjustment_request_edit', ['only' => 'update']);
        $this->middleware('permission:adjustment_request_delete', ['only' => 'destroy']);
        $this->middleware('permission:adjustment_request_approve', ['only' => 'approve']);
    }

    public function index()
    {
        $adjustmentRequests = $this->service->findAllPaginate(
            $this->per_page,
            fn ($query) => $query->with(['user', 'stockProductUnit']),
            [
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('stock_product_unit_id'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
            ],
            [],
            ['id', 'user_id', 'stock_product_unit_id', 'created_at'],
        );

        return AdjustmentRequestResource::collection($adjustmentRequests);
    }

    public function show(AdjustmentRequest $adjustmentRequest)
    {
        return new AdjustmentRequestResource($adjustmentRequest->load('stockProductUnit'));
    }

    public function store(StoreRequest $request)
    {
        $adjustmentRequest = $this->service->create($request->validated());

        return new AdjustmentRequestResource($adjustmentRequest->load('stockProductUnit'));
    }

    public function update(AdjustmentRequest $adjustmentRequest, StoreRequest $request)
    {
        $this->service->updateUnlessApproved($adjustmentRequest, $request->validated());

        return (new AdjustmentRequestResource($adjustmentRequest->load('stockProductUnit')))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(AdjustmentRequest $adjustmentRequest)
    {
        $this->service->deleteUnlessApproved($adjustmentRequest);

        return $this->deletedResponse();
    }

    public function approve(AdjustmentRequest $adjustmentRequest, Request $request)
    {
        $adjustmentRequest = $this->service->decide($adjustmentRequest, $request->reason, $request->is_approved);

        $message = 'Data '.($adjustmentRequest->is_approved ? 'approved' : 'rejected').' successfully';

        return response()->json(['message' => $message]);
    }
}
