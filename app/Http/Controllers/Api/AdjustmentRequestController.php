<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdjustmentRequestStoreRequest;
use App\Http\Resources\AdjustmentRequestResource;
use App\Models\AdjustmentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AdjustmentRequestController extends Controller
{
    public function __construct()
    {
        // $this->middleware('permission:adjustment_request_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:adjustment_request_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:adjustment_request_create', ['only' => 'store']);
        $this->middleware('permission:adjustment_request_edit', ['only' => 'update']);
        $this->middleware('permission:adjustment_request_delete', ['only' => 'destroy']);
        $this->middleware('permission:adjustment_request_approve', ['only' => 'approve']);
    }

    public function index()
    {
        // abort_if(!auth()->user()->tokenCan('adjustment_request_access'), 403);

        $adjustmentRequests = QueryBuilder::for(AdjustmentRequest::with(['user', 'stockProductUnit']))
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('stock_product_unit_id'),
                AllowedFilter::scope('startDate'),
                AllowedFilter::scope('endDate'),
            ])
            ->allowedSorts(['id', 'user_id', 'stock_product_unit_id', 'created_at'])
            ->paginate();

        return AdjustmentRequestResource::collection($adjustmentRequests);
    }

    public function show(AdjustmentRequest $adjustmentRequest)
    {
        // abort_if(!auth()->user()->tokenCan('adjustment_request_access'), 403);
        return new AdjustmentRequestResource($adjustmentRequest->load('stockProductUnit'));
    }

    public function store(AdjustmentRequestStoreRequest $request)
    {
        $adjustmentRequest = AdjustmentRequest::create($request->validated());

        return new AdjustmentRequestResource($adjustmentRequest->load('stockProductUnit'));
    }

    public function update(AdjustmentRequest $adjustmentRequest, AdjustmentRequestStoreRequest $request)
    {
        if ($adjustmentRequest->is_approved) return response()->json(['message' => "Tidak dapat update data jika sudah di approved"], 400);
        $adjustmentRequest->update($request->validated());

        return (new AdjustmentRequestResource($adjustmentRequest->load('stockProductUnit')))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(AdjustmentRequest $adjustmentRequest)
    {
        // abort_if(!auth()->user()->tokenCan('adjustment_request_delete'), 403);
        if ($adjustmentRequest->is_approved) return response()->json(['message' => "Tidak dapat menghapus data jika sudah di approved"], 400);

        $adjustmentRequest->delete();
        return $this->deletedResponse();
    }

    public function approve(AdjustmentRequest $adjustmentRequest, Request $request)
    {
        // abort_if(!auth()->user()->tokenCan('adjustment_request_approve'), 403);

        $stockProductUnit = $adjustmentRequest->stockProductUnit;
        if (!$stockProductUnit) return response()->json(['message' => "Stock product unit Tidak ditemukan"], 404);

        DB::beginTransaction();
        try {
            $adjustmentRequest->reason = $request->reason ?? null;
            $adjustmentRequest->is_approved = $request->is_approved ?? null;
            $adjustmentRequest->approved_by = auth()->user()->id;
            $adjustmentRequest->approved_datetime = now();

            if ($adjustmentRequest->isDirty('is_approved')) {
                if ($adjustmentRequest->is_approved == 1) {
                    $folder = 'qrcode/';
                    for ($i = 0; $i < $adjustmentRequest->value ?? 0; $i++) {
                        $stock = $stockProductUnit->stocks()->create([
                            'adjustment_request_id' => $adjustmentRequest->id
                        ]);

                        $logo = public_path('images/logo-platinum.png');

                        $data = QrCode::size(350)
                            ->format('png')
                            // ->merge($logo, absolute: true)
                            ->generate($stock->id);

                        $fileName = $adjustmentRequest->id . '/' . $stock->id . '.png';
                        $fullPath = $folder .  $fileName;
                        Storage::put($fullPath, $data);

                        $stock->update(['qr_code' => $fullPath]);
                    }

                    $adjustmentRequest->histories()->create([
                        'user_id' => auth()->user()->id,
                        'stock_product_unit_id' => $adjustmentRequest->stock_product_unit_id,
                        'value' => $adjustmentRequest->value ?? 0,
                        'is_increment' => 1,
                        'description' => 'Adjustment request - ' . $adjustmentRequest->description,
                        'ip' => request()->ip(),
                        'agent' => request()->header('user-agent'),
                    ]);
                } else {
                    $adjustmentRequest->stocks?->each->forceDelete();

                    if ($adjustmentRequest->getOriginal('is_approved')) {
                        $adjustmentRequest->histories()->create([
                            'user_id' => auth()->user()->id,
                            'stock_product_unit_id' => $adjustmentRequest->stock_product_unit_id,
                            'value' => $adjustmentRequest->value ?? 0,
                            'is_increment' => 0,
                            'description' => 'Adjustment request - ' . $adjustmentRequest->description,
                            'ip' => request()->ip(),
                            'agent' => request()->header('user-agent'),
                        ]);
                    }

                    Storage::deleteDirectory($adjustmentRequest->id);
                }
            }

            $adjustmentRequest->save();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }

        $message = 'Data ' . ($adjustmentRequest->is_approved ? 'approved' : 'rejected') . ' successfully';
        return response()->json(['message' => $message]);
    }
}
