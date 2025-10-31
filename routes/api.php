<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdjustmentRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeliveryOrderController;
use App\Http\Controllers\Api\DeliveryOrderDetailController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductBrandController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductUnitBlacklistController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\ReceiveOrderController;
use App\Http\Controllers\Api\ReceiveOrderDetailController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\SalesOrderDetailController;
use App\Http\Controllers\Api\SalesOrderItemController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UomController;
use App\Http\Controllers\Api\SocialiteController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\StockHistoryController;
use App\Http\Controllers\Api\StockOpnameController;
use App\Http\Controllers\Api\StockOpnameDetailController;
use App\Http\Controllers\Api\StockOpnameItemController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\UserDiscountController;
use App\Http\Controllers\Api\VoucherCategoryController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\VoucherGenerateBatchController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderDetailController;
use App\Http\Controllers\Api\TemporaryStockController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('phpinfo', [TestController::class, 'phpinfo']);
Route::get('test', [TestController::class, 'index']);
Route::get('stocks/export', [StockController::class, 'export']);
Route::post('stocks/import', [StockController::class, 'import']);
Route::post('auth/token', [AuthController::class, 'token']);
Route::post('auth/register', [AuthController::class, 'register']);

/* Media Social Login */
Route::get('/auth/{provider}', [SocialiteController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'handleProvideCallback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions/all', [PermissionController::class, 'all']);
    Route::apiResource('permissions', PermissionController::class);

    Route::group(['prefix' => 'users/{user}/discounts'], function () {
        Route::get('/', [UserDiscountController::class, 'index']);
        Route::get('{id}', [UserDiscountController::class, 'show']);
        Route::post('/', [UserDiscountController::class, 'store']);
        Route::put('{id}', [UserDiscountController::class, 'update']);
    });

    Route::put('users/{user}/restore', [UserController::class, 'restore']);
    Route::delete('users/{user}/force-delete', [UserController::class, 'forceDelete']);
    Route::get('users/me', [UserController::class, 'me']);
    Route::apiResource('users', UserController::class);

    Route::apiResource('warehouses', WarehouseController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('product-categories', ProductCategoryController::class);
    Route::apiResource('product-brands', ProductBrandController::class);
    Route::get('products/sample-import', [ProductController::class, 'sampleImport']);
    Route::post('products/import', [ProductController::class, 'import']);
    Route::apiResource('products', ProductController::class);

    Route::post('product-units/{productUnit}/create-relations', [ProductUnitController::class, 'createProductUnitRelations']);
    Route::put('product-units/{productUnit}/change-product', [ProductUnitController::class, 'changeProduct']);
    // Route::put('product-units/{productUnit}/set-packaging', [ProductUnitController::class, 'setPackaging']);
    Route::get('product-units/{productUnit}/user-price/{user}', [ProductUnitController::class, 'userPrice']);
    Route::get('product-units/sample-import', [ProductUnitController::class, 'sampleImport']);
    Route::post('product-units/import', [ProductUnitController::class, 'import']);
    Route::apiResource('product-units', ProductUnitController::class);
    Route::apiResource('product-unit-blacklists', ProductUnitBlacklistController::class)->only(['index', 'store', 'destroy']);

    Route::apiResource('uoms', UomController::class);

    Route::group(['prefix' => 'receive-orders/{receiveOrder}/details'], function () {
        Route::get('/', [ReceiveOrderDetailController::class, 'index']);
        Route::get('{receiveOrderDetail}', [ReceiveOrderDetailController::class, 'show']);
        Route::post('/', [ReceiveOrderDetailController::class, 'store']);
        Route::put('{receiveOrderDetail}', [ReceiveOrderDetailController::class, 'update']);
        Route::put('{receiveOrderDetail}/verify', [ReceiveOrderDetailController::class, 'verify']);
        Route::delete('{receiveOrderDetail}', [ReceiveOrderDetailController::class, 'destroy']);
    });

    Route::put('receive-orders/{receiveOrder}/done', [ReceiveOrderController::class, 'done']);
    Route::apiResource('receive-orders', ReceiveOrderController::class);

    Route::group(['prefix' => 'sales-orders/{salesOrder}/details'], function () {
        Route::get('/', [SalesOrderDetailController::class, 'index']);
        Route::get('{salesOrderDetail}', [SalesOrderDetailController::class, 'show']);
        Route::put('{salesOrderDetail}', [SalesOrderDetailController::class, 'update']);
        Route::delete('{salesOrderDetail}', [SalesOrderDetailController::class, 'destroy']);
    });

    // Route::post('sales-orders/invoice', [SalesOrderController::class, 'invoice']);
    Route::get('sales-orders/product-units', [SalesOrderController::class, 'productUnits']);
    Route::get('sales-orders/{salesOrder}/export-xml', [SalesOrderController::class, 'exportXml']);
    Route::get('sales-orders/{salesOrder}/print', [SalesOrderController::class, 'print']); // type (print/print-invoice)
    Route::apiResource('sales-orders', SalesOrderController::class);

    Route::get('invoices/export', [InvoiceController::class, 'export']);
    Route::get('invoices/get-invoice-no', [InvoiceController::class, 'getInvoiceNo']);
    Route::get('invoices/{salesOrder}/export-xml', [InvoiceController::class, 'exportXml']);
    Route::get('invoices/{salesOrder}/bill', [InvoiceController::class, 'bill']);
    Route::apiResource('invoices', InvoiceController::class);

    Route::get('sales-order-items/{salesOrderDetail}', [SalesOrderItemController::class, 'index']);
    Route::post('sales-order-items/{salesOrderDetail}', [SalesOrderItemController::class, 'store']);
    Route::delete('sales-order-items/{salesOrderDetail}', [SalesOrderItemController::class, 'destroy']);

    Route::group(['prefix' => 'delivery-orders/{deliveryOrder}/details'], function () {
        Route::get('/', [DeliveryOrderDetailController::class, 'index']);
        Route::get('{deliveryOrderDetail}', [DeliveryOrderDetailController::class, 'show']);
        Route::put('{deliveryOrderDetail}/reset-verified-stock', [DeliveryOrderDetailController::class, 'resetVerifiedStock']);
        Route::delete('{deliveryOrderDetail}', [DeliveryOrderDetailController::class, 'destroy']);
    });

    Route::post('delivery-orders/{deliveryOrder}/attach', [DeliveryOrderController::class, 'attach']);
    Route::apiResource('delivery-orders', DeliveryOrderController::class);
    // Route::post('delivery-orders/{deliveryOrder}/verification/{salesOrderDetail}', [DeliveryOrderController::class, 'verification']);
    Route::post('delivery-orders/{deliveryOrder}/verification/{deliveryOrderDetail}', [DeliveryOrderController::class, 'verification']);
    Route::get('delivery-orders/{deliveryOrder}/print', [DeliveryOrderController::class, 'print']);
    Route::put('delivery-orders/{deliveryOrder}/done', [DeliveryOrderController::class, 'done']);
    Route::get('delivery-orders/{deliveryOrder}/export-xml', [DeliveryOrderController::class, 'exportXml']);

    Route::put('adjustment-requests/{adjustmentRequest}/approve', [AdjustmentRequestController::class, 'approve']);
    Route::apiResource('adjustment-requests', AdjustmentRequestController::class);

    Route::get('stocks/details', [StockController::class, 'details']);
    Route::get('stocks/print-all', [StockController::class, 'printAll']);
    Route::put('stocks/verification-tempel', [StockController::class, 'verificationTempel']);
    Route::post('stocks/add-to-stock', [StockController::class, 'addToStock']);
    Route::post('stocks/set-to-printed', [StockController::class, 'setToPrinted']);
    Route::post('stocks/set-to-printing-queue', [StockController::class, 'setToPrintingQueue']);
    Route::post('stocks/print-verification', [StockController::class, 'printVerification']);
    Route::post('stocks/record', [StockController::class, 'record']);
    Route::post('stocks/grouping', [StockController::class, 'grouping']);
    Route::post('stocks/grouping-by-scan', [StockController::class, 'groupingByScan']);
    Route::post('stocks/{stock}/ungrouping', [StockController::class, 'ungrouping']);
    Route::post('stocks/{stock}/repack', [StockController::class, 'repack']);
    Route::apiResource('stocks', StockController::class);

    Route::group(['prefix' => 'stock-opnames/{stockOpname}/details'], function () {
        Route::get('{stockOpnameDetail}/items', [StockOpnameItemController::class, 'index']);
        Route::delete('{stockOpnameDetail}/items', [StockOpnameItemController::class, 'destroy']);

        Route::get('/', [StockOpnameDetailController::class, 'index']);
        Route::get('{stockOpnameDetail}', [StockOpnameDetailController::class, 'show']);
        Route::put('{stockOpnameDetail}/done', [StockOpnameDetailController::class, 'done']);
        Route::put('{stockOpnameDetail}/scan', [StockOpnameDetailController::class, 'scan']);
        Route::put('{stockOpnameDetail}', [StockOpnameDetailController::class, 'update']);
        Route::delete('{stockOpnameDetail}', [StockOpnameDetailController::class, 'destroy']);
    });

    Route::put('stock-opnames/{stockOpname}/done', [StockOpnameController::class, 'done']);
    Route::put('stock-opnames/{stockOpname}/set-done', [StockOpnameController::class, 'setDone']);
    Route::apiResource('stock-opnames', StockOpnameController::class);

    // Route::apiResource('stock-histories', StockHistoryController::class);
    Route::get('stock-histories/export', [StockHistoryController::class, 'export']);
    Route::get('stock-histories', [StockHistoryController::class, 'index']);

    Route::apiResource('settings', SettingController::class)->only(['index', 'update']);

    Route::group(['prefix' => 'payments/{payment}'], function () {
        Route::put('restore', [PaymentController::class, 'restore']);
        Route::delete('force-delete', [PaymentController::class, 'forceDelete']);
    });
    Route::apiResource('payments', PaymentController::class);

    Route::group(['prefix' => 'voucher-categories/{voucher_category}'], function () {
        Route::put('restore', [VoucherCategoryController::class, 'restore']);
        Route::delete('force-delete', [VoucherCategoryController::class, 'forceDelete']);
    });
    Route::apiResource('voucher-categories', VoucherCategoryController::class);

    Route::group(['prefix' => 'vouchers'], function () {
        Route::apiResource('generate-batches', VoucherGenerateBatchController::class);

        Route::post('import', [VoucherController::class, 'import']);
        Route::group(['prefix' => '{voucher}'], function () {
            Route::put('restore', [VoucherController::class, 'restore']);
            Route::delete('force-delete', [VoucherController::class, 'forceDelete']);
        });
    });
    Route::apiResource('vouchers', VoucherController::class);

    Route::get('exports/sample/{type}', [\App\Http\Controllers\Api\ExportController::class, 'sample']);

    Route::group(['prefix' => 'orders/{order}/details'], function () {
        Route::get('/', [OrderDetailController::class, 'index']);
        Route::get('{orderDetail}', [OrderDetailController::class, 'show']);
        Route::put('{orderDetail}', [OrderDetailController::class, 'update']);
        Route::delete('{orderDetail}', [OrderDetailController::class, 'destroy']);
    });

    Route::put('orders/{order}/convert-so', [OrderController::class, 'convertSalesOrder']);
    // Route::get('orders/{order}/print', [OrderController::class, 'print']); // type (print/print-invoice)
    Route::apiResource('orders', OrderController::class);

    Route::get('temporary-stocks', [TemporaryStockController::class, 'index']);
    Route::post('temporary-stocks', [TemporaryStockController::class, 'store']);
});
