<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Stock\AddToStockRequest;
use App\Http\Requests\Api\Stock\GroupingByScanRequest;
use App\Http\Requests\Api\Stock\GroupingRequest;
use App\Http\Requests\Api\Stock\RecordRequest;
use App\Http\Requests\Api\Stock\RepackRequest;
use App\Http\Requests\Api\Stock\SetToPrintingQueueRequest;
use App\Http\Requests\Api\Stock\UpdateRequest;
use App\Http\Requests\Api\Stock\VerificationTempelRequest;
use App\Http\Requests\Api\Stock\VerifyRequest;
use App\Http\Resources\DefaultResource;
use App\Http\Resources\StockProductUnitResource;
use App\Http\Resources\Stocks\BaseStockResource;
use App\Http\Resources\Stocks\StockProductUnitResource as StocksStockProductUnitResource;
use App\Interfaces\Services\Stock\StockGroupingServiceInterface;
use App\Interfaces\Services\Stock\StockRepackServiceInterface;
use App\Interfaces\Services\Stock\StockServiceInterface;
use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends BaseController
{
    public function __construct(
        private StockServiceInterface $service,
        private StockGroupingServiceInterface $groupingService,
        private StockRepackServiceInterface $repackService,
    ) {
        parent::__construct();
        // $this->middleware('permission:stock_access', ['only' => ['index', 'show', 'details']]);
        $this->middleware('permission:stock_read', ['only' => ['index', 'show', 'details']]);
        // 'stock_create' is also used for 'import' — importing stock is a
        // bulk-create. No 'stock_import' permission exists in the seeded
        // taxonomy; flagged in the report rather than inventing one.
        $this->middleware('permission:stock_create', ['only' => ['store', 'import']]);
        $this->middleware('permission:stock_edit', ['only' => 'update']);
        $this->middleware('permission:stock_delete', ['only' => 'destroy']);
        $this->middleware('permission:stock_grouping', ['only' => ['grouping', 'ungrouping']]);
        $this->middleware('permission:stock_print', ['only' => 'printAll']);
        // 'stock_export' already existed in PermissionsHelper::adminPermissions()
        // (seeded by PermissionSeeder) but was never wired to a middleware.
        $this->middleware('permission:stock_export', ['only' => 'export']);
    }

    public function index()
    {
        $stockProductUnits = $this->service->paginateStockProductUnits($this->per_page);

        return StockProductUnitResource::collection($stockProductUnits);
    }

    public function details()
    {
        $stocks = $this->service->paginateDetails(request()->input('filter', []), $this->per_page);

        return BaseStockResource::collection($stocks);
    }

    public function show(string $id, Request $request)
    {
        $stock = $this->service->findForShow($id, $request->query('include', ''));

        return new StocksStockProductUnitResource($stock);
    }

    public function update(UpdateRequest $request, Stock $stock)
    {
        $this->service->updateWithChildren($stock, $request->validated());

        return response()->json(['message' => 'Data updated successfully']);
    }

    public function destroy(Stock $stock)
    {
        $this->service->deleteSingle($stock);

        return $this->deletedResponse();
    }

    public function bulkDestroy(Request $request)
    {
        $this->service->bulkDelete($request->ids ?? []);

        return $this->deletedResponse();
    }

    public function groupingByScan(GroupingByScanRequest $request)
    {
        $this->groupingService->groupByScan($request->input());

        return response()->json(['message' => 'Group stock berhasil dibuat'], 201);
    }

    public function grouping(GroupingRequest $request)
    {
        $this->groupingService->group($request->input());

        return response()->json(['message' => 'Group stock berhasil dibuat'], 201);
    }

    public function ungrouping(string $id)
    {
        $this->groupingService->ungroup($id);

        return response()->json(['message' => 'success']);
    }

    public function printAll(Request $request)
    {
        $stocks = $this->service->printAll($request->input('filter', []), $request->print_with_parent);

        return response()->json($stocks);
    }

    public function record(RecordRequest $request)
    {
        $message = $this->service->record(
            $request->stock_ids,
            $request->stock_product_unit_id,
            $request->receive_order_detail_id,
            $request->is_print_all,
        );

        return response()->json(['message' => $message]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xls,xlsx,csv',
        ]);

        $this->service->import($request->file, $request->warehouse_id);

        return $this->createdResponse('Data imported successfully', 200);
    }

    public function verificationTempel(VerificationTempelRequest $request)
    {
        $this->service->setTempel($request->ids, $request->is_tempel);

        return $this->updatedResponse('Stock berhasil diupdate '.($request->is_tempel ? 'Terpasang' : 'Belum Terpasang'));
    }

    public function repack($id, RepackRequest $request)
    {
        $message = $this->repackService->repack($id, $request->qty, $request->stock_product_unit_id, $request->created_at);

        return response()->json(['message' => $message]);
    }

    public function export()
    {
        return $this->service->export();
    }

    public function setToPrinted(VerifyRequest $request)
    {
        $this->service->setToPrinted($request->stocks);

        return response()->json([
            'message' => count($request->stocks).' stocks set to printed successfully',
        ]);
    }

    public function setToPrintingQueue(SetToPrintingQueueRequest $request)
    {
        $this->service->setToPrintingQueue($request->stocks, $request->printer_id, $request->expired_date);

        return response()->json([
            'message' => count($request->stocks).' stocks added to printing queue',
        ]);
    }

    public function printVerification(VerifyRequest $request)
    {
        if ($request->is_preview) {
            return DefaultResource::collection($this->service->findPrintedForPreview($request->stocks));
        }

        $this->service->setToPrinted($request->stocks);

        return response()->json([
            'message' => count($request->stocks).' stocks scanned successfully',
        ]);
    }

    public function addToStock(AddToStockRequest $request)
    {
        $this->service->setIsStock($request->ids, $request->is_add);

        return response()->json([
            'message' => count($request->ids).' stocks '.($request->is_add ? 'added' : 'removed').' successfully',
        ]);
    }

    public function exportStocksTxt(Request $request)
    {
        $request->validate([
            'stock_ids' => 'required|array',
            'stock_ids.*' => 'string',
        ]);

        $content = $this->service->idsAsText($request->stock_ids);

        $fileName = 'stocks-'.date('Y-m-d_His').'.txt';

        return response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }
}
