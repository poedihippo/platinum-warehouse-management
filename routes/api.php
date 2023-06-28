<?php

use App\Http\Controllers\Api\AdjustmentRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeliveryOrderController;
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
use App\Http\Controllers\Api\StockOpnameController;
use App\Http\Controllers\Api\StockOpnameDetailController;
use App\Http\Controllers\Api\StockOpnameItemController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\UserDiscountController;
use Illuminate\Support\Facades\Route;

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
Route::post('auth/token', [AuthController::class, 'token']);
Route::post('auth/register', [AuthController::class, 'register']);

/* Media Social Login */
Route::get('/auth/{provider}', [SocialiteController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'handleProvideCallback']);

Route::middleware('auth:sanctum')->group(function ($route) {
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);

    Route::group(['prefix' => 'users/{user}/discounts'], function () {
        Route::get('/', [UserDiscountController::class, 'index']);
        Route::get('{id}', [UserDiscountController::class, 'show']);
        Route::post('/', [UserDiscountController::class, 'store']);
        Route::put('{id}', [UserDiscountController::class, 'update']);
    });

    Route::put('users/{user}/restore', [UserController::class, 'restore']);
    Route::delete('users/{user}/force-delete', [UserController::class, 'forceDelete']);
    Route::get('users/me', [UserController::class, 'me']);
    Route::resource('users', UserController::class);

    Route::resource('warehouses', WarehouseController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('product-categories', ProductCategoryController::class);
    Route::resource('product-brands', ProductBrandController::class);
    Route::resource('products', ProductController::class);
    Route::resource('product-units', ProductUnitController::class);
    Route::resource('product-units', ProductUnitController::class);
    Route::resource('product-unit-blacklists', ProductUnitBlacklistController::class)->only(['index', 'store', 'destroy']);

    Route::resource('uoms', UomController::class);

    Route::group(['prefix' => 'receive-orders/{receiveOrder}/details'], function () {
        Route::get('/', [ReceiveOrderDetailController::class, 'index']);
        Route::get('{receiveOrderDetail}', [ReceiveOrderDetailController::class, 'show']);
        Route::post('/', [ReceiveOrderDetailController::class, 'store']);
        Route::put('{receiveOrderDetail}', [ReceiveOrderDetailController::class, 'update']);
        Route::put('{receiveOrderDetail}/verify', [ReceiveOrderDetailController::class, 'verify']);
        Route::delete('{receiveOrderDetail}', [ReceiveOrderDetailController::class, 'destroy']);
    });

    Route::put('receive-orders/{receiveOrder}/done', [ReceiveOrderController::class, 'done']);
    Route::resource('receive-orders', ReceiveOrderController::class);

    Route::group(['prefix' => 'sales-orders/{salesOrder}/details'], function () {
        Route::get('/', [SalesOrderDetailController::class, 'index']);
        Route::get('{salesOrderDetail}', [SalesOrderDetailController::class, 'show']);
        Route::put('{salesOrderDetail}', [SalesOrderDetailController::class, 'update']);
        Route::delete('{salesOrderDetail}', [SalesOrderDetailController::class, 'destroy']);
    });

    // Route::get('sales-orders/get-price', [SalesOrderController::class, 'getPrice']);
    Route::get('sales-orders/product-units', [SalesOrderController::class, 'productUnits']);
    Route::get('sales-orders/{salesOrder}/print', [SalesOrderController::class, 'print']);
    Route::get('sales-orders/{salesOrder}/export-xml', [SalesOrderController::class, 'exportXml']);
    Route::resource('sales-orders', SalesOrderController::class);

    Route::get('sales-order-items/{salesOrderDetail}', [SalesOrderItemController::class, 'index']);
    Route::post('sales-order-items/{salesOrderDetail}', [SalesOrderItemController::class, 'store']);
    Route::delete('sales-order-items/{salesOrderDetail}', [SalesOrderItemController::class, 'destroy']);

    Route::resource('delivery-orders', DeliveryOrderController::class);
    Route::post('delivery-orders/{deliveryOrder}/verification/{salesOrderDetail}', [DeliveryOrderController::class, 'verification']);
    Route::get('delivery-orders/{deliveryOrder}/print', [DeliveryOrderController::class, 'print']);
    Route::put('delivery-orders/{deliveryOrder}/done', [DeliveryOrderController::class, 'done']);
    Route::get('delivery-orders/{deliveryOrder}/export-xml', [DeliveryOrderController::class, 'exportXml']);

    Route::put('adjustment-requests/{adjustmentRequest}/approve', [AdjustmentRequestController::class, 'approve']);
    Route::resource('adjustment-requests', AdjustmentRequestController::class);

    Route::post('stocks/record', [StockController::class, 'record']);
    Route::post('stocks/grouping', [StockController::class, 'grouping']);
    Route::post('stocks/{stock}/ungrouping', [StockController::class, 'ungrouping']);
    Route::get('stocks/details', [StockController::class, 'details']);
    Route::get('stocks/print-all', [StockController::class, 'printAll']);
    Route::resource('stocks', StockController::class);

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
    Route::resource('stock-opnames', StockOpnameController::class);

    Route::resource('settings', SettingController::class)->only(['index', 'update']);
});
