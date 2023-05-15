<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductBrandController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\ReceiveOrderController;
use App\Http\Controllers\Api\ReceiveOrderDetailController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UomController;
use App\Http\Controllers\Api\SocialiteController;
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

Route::post('auth/token', [AuthController::class, 'token']);
Route::post('auth/register', [AuthController::class, 'register']);

/* Media Social Login */
Route::get('/auth/{provider}', [SocialiteController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [SocialiteController::class, 'handleProvideCallback']);

Route::middleware('auth:sanctum')->group(function ($route) {
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);
    Route::get('users/me', [UserController::class, 'me']);
    Route::resource('users', UserController::class);
    Route::resource('warehouses', WarehouseController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('product-categories', ProductCategoryController::class);
    Route::resource('product-brands', ProductBrandController::class);
    Route::resource('products', ProductController::class);
    Route::resource('product-units', ProductUnitController::class);
    Route::resource('product-units', ProductUnitController::class);
    Route::resource('uoms', UomController::class);

    Route::group(['prefix' => 'receive-orders/{receiveOrder}/details'], function () {
        Route::get('/', [ReceiveOrderDetailController::class, 'index']);
        Route::get('{receiveOrderDetail}', [ReceiveOrderDetailController::class, 'show']);
        // Route::post('/', [ReceiveOrderDetailController::class, 'store']);
        Route::put('{receiveOrderDetail}', [ReceiveOrderDetailController::class, 'update']);
        Route::put('{receiveOrderDetail}/verify', [ReceiveOrderDetailController::class, 'verify']);
        Route::delete('{receiveOrderDetail}', [ReceiveOrderDetailController::class, 'destroy']);
    });
    Route::resource('receive-orders', ReceiveOrderController::class);
});
