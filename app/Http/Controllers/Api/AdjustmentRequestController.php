<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdjustmentRequestStoreRequest;
use App\Http\Resources\AdjustmentRequestResource;
use App\Models\AdjustmentRequest;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AdjustmentRequestController extends Controller
{
    public function __construct()
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
        // abort_if(!auth('sanctum')->user()->tokenCan('adjustment_request_access'), 403);

        $adjustmentRequests = QueryBuilder::for(AdjustmentRequest::with(['user', 'stockProductUnit']))
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('stock_product_unit_id'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
            ])
            ->allowedSorts(['id', 'user_id', 'stock_product_unit_id', 'created_at'])
            ->paginate($this->per_page);

        return AdjustmentRequestResource::collection($adjustmentRequests);
    }

    public function show(AdjustmentRequest $adjustmentRequest)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('adjustment_request_access'), 403);
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
        // abort_if(!auth('sanctum')->user()->tokenCan('adjustment_request_delete'), 403);
        if ($adjustmentRequest->is_approved) return response()->json(['message' => "Tidak dapat menghapus data jika sudah di approved"], 400);

        $adjustmentRequest->delete();
        return $this->deletedResponse();
    }

    public function approve(AdjustmentRequest $adjustmentRequest, Request $request)
    {
        // abort_if(!auth('sanctum')->user()->tokenCan('adjustment_request_approve'), 403);

        if (!is_null($adjustmentRequest->is_approved)) return response()->json(['message' => sprintf("Adjustment request sudah di %s. Tidak dapat di edit!", $adjustmentRequest->is_approved ? 'approve' : 'reject')], 404);

        /** @var \App\Models\StockProductUnit $stockProductUnit */
        $stockProductUnit = $adjustmentRequest->stockProductUnit;
        if (!$stockProductUnit) return response()->json(['message' => "Stock product unit tidak ditemukan"], 404);

        DB::beginTransaction();
        try {
            $adjustmentRequest->reason = $request->reason ?? null;
            $adjustmentRequest->is_approved = $request->is_approved ?? null;
            $adjustmentRequest->approved_by = auth('sanctum')->id();
            $adjustmentRequest->approved_datetime = now();

            if ($adjustmentRequest->is_approved == true) {
                if ($adjustmentRequest->is_increment == 1) {
                    // $folder = 'qrcode/';

                    if ($stockProductUnit->productUnit->is_generate_qr) {
                        // $isStock = $stockProductUnit->productUnit->is_auto_stock;
                        // GenerateStockQrcode::dispatch($stockProductUnit, $qty, $folder);

                        for ($i = 0; $i < $adjustmentRequest->value ?? 0; $i++) {
                            $stockProductUnit->stocks()->create([
                                'adjustment_request_id' => $adjustmentRequest->id,
                                // 'is_stock' => $isStock,
                                'description' => null
                            ]);

                            // $logo = public_path('images/logo-platinum.png');

                            // $data = QrCode::size(350)
                            //     ->format('png')
                            //     // ->merge($logo, absolute: true)
                            //     ->generate($stock->id);

                            // $fileName = $adjustmentRequest->id . '/' . $stock->id . '.png';
                            // $fullPath = $folder .  $fileName;
                            // Storage::put($fullPath, $data);

                            // $stock->update(['qr_code' => $fullPath]);
                        }
                    } else {
                        $stockProductUnit->increment('qty', $adjustmentRequest->value);
                    }

                    $adjustmentRequest->histories()->create([
                        'user_id' => auth('sanctum')->id(),
                        'stock_product_unit_id' => $adjustmentRequest->stock_product_unit_id,
                        'value' => $adjustmentRequest->value ?? 0,
                        'is_increment' => 1,
                        'description' => 'Adjustment request (Penambahan) - ' . $adjustmentRequest->description,
                        'ip' => request()->ip(),
                        'agent' => request()->header('user-agent'),
                    ]);
                } else {
                    if ($stockProductUnit->productUnit->is_generate_qr) {
                        $adjustmentRequest->stocks?->each->forceDelete();
                        Stock::whereAvailableStock()->where('stock_product_unit_id', $stockProductUnit->id)->limit($adjustmentRequest->value)->delete();

                        // Storage::deleteDirectory($adjustmentRequest->id);
                    } else {
                        $stockProductUnit->decrement('qty', $adjustmentRequest->value);
                    }

                    // if ($adjustmentRequest->getOriginal('is_approved')) {
                    $adjustmentRequest->histories()->create([
                        'user_id' => auth('sanctum')->id(),
                        'stock_product_unit_id' => $adjustmentRequest->stock_product_unit_id,
                        'value' => $adjustmentRequest->value ?? 0,
                        'is_increment' => 0,
                        'description' => 'Adjustment request (Pengurangan) - ' . $adjustmentRequest->description,
                        'ip' => request()->ip(),
                        'agent' => request()->header('user-agent'),
                    ]);
                    // }
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
