<?php

namespace App\Providers;

use App\Http\Repositories\AdjustmentRequest\AdjustmentRequestRepository;
use App\Http\Repositories\DeliveryOrder\DeliveryOrderRepository;
use App\Http\Repositories\DocumentCounter\DocumentCounterRepository;
use App\Http\Repositories\Loyalty\Claim\ClaimRepository;
use App\Http\Repositories\Loyalty\LoyaltyUser\LoyaltyUserRepository;
use App\Http\Repositories\Loyalty\PointsTransaction\PointsTransactionRepository;
use App\Http\Repositories\Loyalty\Prize\PrizeRepository;
use App\Http\Repositories\Loyalty\PrizeCategory\PrizeCategoryRepository;
use App\Http\Repositories\Loyalty\Redemption\RedemptionRepository;
use App\Http\Repositories\Payment\PaymentRepository;
use App\Http\Repositories\Permission\PermissionRepository;
use App\Http\Repositories\Product\ProductRepository;
use App\Http\Repositories\ProductBrand\ProductBrandRepository;
use App\Http\Repositories\ProductCategory\ProductCategoryRepository;
use App\Http\Repositories\ProductUnit\ProductUnitRepository;
use App\Http\Repositories\ProductUnitBlacklist\ProductUnitBlacklistRepository;
use App\Http\Repositories\ReceiveOrder\ReceiveOrderDetailRepository;
use App\Http\Repositories\ReceiveOrder\ReceiveOrderRepository;
use App\Http\Repositories\Role\RoleRepository;
use App\Http\Repositories\SalesOrder\SalesOrderRepository;
use App\Http\Repositories\Setting\SettingRepository;
use App\Http\Repositories\Stock\StockRepository;
use App\Http\Repositories\StockHistory\StockHistoryRepository;
use App\Http\Repositories\StockOpname\StockOpnameRepository;
use App\Http\Repositories\StockProductUnit\StockProductUnitRepository;
use App\Http\Repositories\StockTransfer\StockTransferRepository;
use App\Http\Repositories\Supplier\SupplierRepository;
use App\Http\Repositories\TemporaryStock\TemporaryStockRepository;
use App\Http\Repositories\Uom\UomRepository;
use App\Http\Repositories\User\UserRepository;
use App\Http\Repositories\UserDiscount\UserDiscountRepository;
use App\Http\Repositories\Voucher\VoucherRepository;
use App\Http\Repositories\VoucherCategory\VoucherCategoryRepository;
use App\Http\Repositories\VoucherGenerateBatch\VoucherGenerateBatchRepository;
use App\Http\Repositories\Warehouse\WarehouseRepository;
use App\Http\Services\AdjustmentRequest\AdjustmentRequestService;
use App\Http\Services\Auth\AuthService;
use App\Http\Services\DeliveryOrder\DeliveryOrderDetailService;
use App\Http\Services\DeliveryOrder\DeliveryOrderService;
use App\Http\Services\DocumentCounter\DocumentCounterService;
use App\Http\Services\DocumentCounter\NumberingService;
use App\Http\Services\Export\ExportService;
use App\Http\Services\Invoice\InvoiceNumberService;
use App\Http\Services\Invoice\InvoiceService;
use App\Http\Services\Loyalty\Claim\ClaimReviewService;
use App\Http\Services\Loyalty\Claim\ClaimService;
use App\Http\Services\Loyalty\LoyaltyUser\CustomerManagementService;
use App\Http\Services\Loyalty\LoyaltyUser\LoyaltyAuthService;
use App\Http\Services\Loyalty\LoyaltyUser\LoyaltyProfileService;
use App\Http\Services\Loyalty\PointsTransaction\PointsService;
use App\Http\Services\Loyalty\Prize\PrizeService;
use App\Http\Services\Loyalty\PrizeCategory\PrizeCategoryService;
use App\Http\Services\Loyalty\Redemption\RedemptionReviewService;
use App\Http\Services\Loyalty\Redemption\RedemptionService;
use App\Http\Services\Order\OrderService;
use App\Http\Services\Payment\PaymentService;
use App\Http\Services\Permission\PermissionService;
use App\Http\Services\Product\ProductService;
use App\Http\Services\ProductBrand\ProductBrandService;
use App\Http\Services\ProductCategory\ProductCategoryService;
use App\Http\Services\ProductUnit\ProductUnitService;
use App\Http\Services\ProductUnitBlacklist\ProductUnitBlacklistService;
use App\Http\Services\ReceiveOrder\ReceiveOrderDetailService;
use App\Http\Services\ReceiveOrder\ReceiveOrderService;
use App\Http\Services\Role\RoleService;
use App\Http\Services\SalesOrder\SalesOrderService;
use App\Http\Services\Setting\SettingService;
use App\Http\Services\Stock\StockGroupingService;
use App\Http\Services\Stock\StockRepackService;
use App\Http\Services\Stock\StockService;
use App\Http\Services\StockHistory\StockHistoryService;
use App\Http\Services\StockOpname\StockOpnameDetailService;
use App\Http\Services\StockOpname\StockOpnameService;
use App\Http\Services\StockProductUnit\StockProductUnitService;
use App\Http\Services\StockTransfer\StockTransferService;
use App\Http\Services\Supplier\SupplierService;
use App\Http\Services\TemporaryStock\TemporaryStockService;
use App\Http\Services\Uom\UomService;
use App\Http\Services\User\UserService;
use App\Http\Services\UserDiscount\UserDiscountService;
use App\Http\Services\Voucher\VoucherService;
use App\Http\Services\VoucherCategory\VoucherCategoryService;
use App\Http\Services\VoucherGenerateBatch\VoucherGenerateBatchService;
use App\Http\Services\Warehouse\WarehouseService;
use App\Interfaces\Repositories\AdjustmentRequest\AdjustmentRequestRepositoryInterface;
use App\Interfaces\Repositories\DeliveryOrder\DeliveryOrderRepositoryInterface;
use App\Interfaces\Repositories\DocumentCounter\DocumentCounterRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\Claim\ClaimRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\LoyaltyUser\LoyaltyUserRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\PointsTransaction\PointsTransactionRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\Prize\PrizeRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\PrizeCategory\PrizeCategoryRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\Redemption\RedemptionRepositoryInterface;
use App\Interfaces\Repositories\Payment\PaymentRepositoryInterface;
use App\Interfaces\Repositories\Permission\PermissionRepositoryInterface;
use App\Interfaces\Repositories\Product\ProductRepositoryInterface;
use App\Interfaces\Repositories\ProductBrand\ProductBrandRepositoryInterface;
use App\Interfaces\Repositories\ProductCategory\ProductCategoryRepositoryInterface;
use App\Interfaces\Repositories\ProductUnit\ProductUnitRepositoryInterface;
use App\Interfaces\Repositories\ProductUnitBlacklist\ProductUnitBlacklistRepositoryInterface;
use App\Interfaces\Repositories\ReceiveOrder\ReceiveOrderDetailRepositoryInterface;
use App\Interfaces\Repositories\ReceiveOrder\ReceiveOrderRepositoryInterface;
use App\Interfaces\Repositories\Role\RoleRepositoryInterface;
use App\Interfaces\Repositories\SalesOrder\SalesOrderRepositoryInterface;
use App\Interfaces\Repositories\Setting\SettingRepositoryInterface;
use App\Interfaces\Repositories\Stock\StockRepositoryInterface;
use App\Interfaces\Repositories\StockHistory\StockHistoryRepositoryInterface;
use App\Interfaces\Repositories\StockOpname\StockOpnameRepositoryInterface;
use App\Interfaces\Repositories\StockProductUnit\StockProductUnitRepositoryInterface;
use App\Interfaces\Repositories\StockTransfer\StockTransferRepositoryInterface;
use App\Interfaces\Repositories\Supplier\SupplierRepositoryInterface;
use App\Interfaces\Repositories\TemporaryStock\TemporaryStockRepositoryInterface;
use App\Interfaces\Repositories\Uom\UomRepositoryInterface;
use App\Interfaces\Repositories\User\UserRepositoryInterface;
use App\Interfaces\Repositories\UserDiscount\UserDiscountRepositoryInterface;
use App\Interfaces\Repositories\Voucher\VoucherRepositoryInterface;
use App\Interfaces\Repositories\VoucherCategory\VoucherCategoryRepositoryInterface;
use App\Interfaces\Repositories\VoucherGenerateBatch\VoucherGenerateBatchRepositoryInterface;
use App\Interfaces\Repositories\Warehouse\WarehouseRepositoryInterface;
use App\Interfaces\Services\AdjustmentRequest\AdjustmentRequestServiceInterface;
use App\Interfaces\Services\Auth\AuthServiceInterface;
use App\Interfaces\Services\DeliveryOrder\DeliveryOrderDetailServiceInterface;
use App\Interfaces\Services\DeliveryOrder\DeliveryOrderServiceInterface;
use App\Interfaces\Services\DocumentCounter\DocumentCounterServiceInterface;
use App\Interfaces\Services\DocumentCounter\NumberingServiceInterface;
use App\Interfaces\Services\Export\ExportServiceInterface;
use App\Interfaces\Services\Invoice\InvoiceNumberServiceInterface;
use App\Interfaces\Services\Invoice\InvoiceServiceInterface;
use App\Interfaces\Services\Loyalty\Claim\ClaimReviewServiceInterface;
use App\Interfaces\Services\Loyalty\Claim\ClaimServiceInterface;
use App\Interfaces\Services\Loyalty\LoyaltyUser\CustomerManagementServiceInterface;
use App\Interfaces\Services\Loyalty\LoyaltyUser\LoyaltyAuthServiceInterface;
use App\Interfaces\Services\Loyalty\LoyaltyUser\LoyaltyProfileServiceInterface;
use App\Interfaces\Services\Loyalty\PointsTransaction\PointsServiceInterface;
use App\Interfaces\Services\Loyalty\Prize\PrizeServiceInterface;
use App\Interfaces\Services\Loyalty\PrizeCategory\PrizeCategoryServiceInterface;
use App\Interfaces\Services\Loyalty\Redemption\RedemptionReviewServiceInterface;
use App\Interfaces\Services\Loyalty\Redemption\RedemptionServiceInterface;
use App\Interfaces\Services\Order\OrderServiceInterface;
use App\Interfaces\Services\Payment\PaymentServiceInterface;
use App\Interfaces\Services\Permission\PermissionServiceInterface;
use App\Interfaces\Services\Product\ProductServiceInterface;
use App\Interfaces\Services\ProductBrand\ProductBrandServiceInterface;
use App\Interfaces\Services\ProductCategory\ProductCategoryServiceInterface;
use App\Interfaces\Services\ProductUnit\ProductUnitServiceInterface;
use App\Interfaces\Services\ProductUnitBlacklist\ProductUnitBlacklistServiceInterface;
use App\Interfaces\Services\ReceiveOrder\ReceiveOrderDetailServiceInterface;
use App\Interfaces\Services\ReceiveOrder\ReceiveOrderServiceInterface;
use App\Interfaces\Services\Role\RoleServiceInterface;
use App\Interfaces\Services\SalesOrder\SalesOrderServiceInterface;
use App\Interfaces\Services\Setting\SettingServiceInterface;
use App\Interfaces\Services\Stock\StockGroupingServiceInterface;
use App\Interfaces\Services\Stock\StockRepackServiceInterface;
use App\Interfaces\Services\Stock\StockServiceInterface;
use App\Interfaces\Services\StockHistory\StockHistoryServiceInterface;
use App\Interfaces\Services\StockOpname\StockOpnameDetailServiceInterface;
use App\Interfaces\Services\StockOpname\StockOpnameServiceInterface;
use App\Interfaces\Services\StockProductUnit\StockProductUnitServiceInterface;
use App\Interfaces\Services\StockTransfer\StockTransferServiceInterface;
use App\Interfaces\Services\Supplier\SupplierServiceInterface;
use App\Interfaces\Services\TemporaryStock\TemporaryStockServiceInterface;
use App\Interfaces\Services\Uom\UomServiceInterface;
use App\Interfaces\Services\User\UserServiceInterface;
use App\Interfaces\Services\UserDiscount\UserDiscountServiceInterface;
use App\Interfaces\Services\Voucher\VoucherServiceInterface;
use App\Interfaces\Services\VoucherCategory\VoucherCategoryServiceInterface;
use App\Interfaces\Services\VoucherGenerateBatch\VoucherGenerateBatchServiceInterface;
use App\Interfaces\Services\Warehouse\WarehouseServiceInterface;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Service and repository interface bindings.
     *
     * @var array<class-string, class-string>
     */
    public $singletons = [
        UomRepositoryInterface::class => UomRepository::class,
        UomServiceInterface::class => UomService::class,
        SupplierRepositoryInterface::class => SupplierRepository::class,
        SupplierServiceInterface::class => SupplierService::class,
        ProductCategoryRepositoryInterface::class => ProductCategoryRepository::class,
        ProductCategoryServiceInterface::class => ProductCategoryService::class,
        ProductBrandRepositoryInterface::class => ProductBrandRepository::class,
        ProductBrandServiceInterface::class => ProductBrandService::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        ProductServiceInterface::class => ProductService::class,
        WarehouseRepositoryInterface::class => WarehouseRepository::class,
        WarehouseServiceInterface::class => WarehouseService::class,
        ProductUnitBlacklistRepositoryInterface::class => ProductUnitBlacklistRepository::class,
        ProductUnitBlacklistServiceInterface::class => ProductUnitBlacklistService::class,
        SettingRepositoryInterface::class => SettingRepository::class,
        SettingServiceInterface::class => SettingService::class,
        ExportServiceInterface::class => ExportService::class,

        PermissionRepositoryInterface::class => PermissionRepository::class,
        PermissionServiceInterface::class => PermissionService::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        RoleServiceInterface::class => RoleService::class,
        UserRepositoryInterface::class => UserRepository::class,
        UserServiceInterface::class => UserService::class,
        UserDiscountRepositoryInterface::class => UserDiscountRepository::class,
        UserDiscountServiceInterface::class => UserDiscountService::class,
        AuthServiceInterface::class => AuthService::class,
        DocumentCounterRepositoryInterface::class => DocumentCounterRepository::class,
        DocumentCounterServiceInterface::class => DocumentCounterService::class,
        NumberingServiceInterface::class => NumberingService::class,

        ProductUnitRepositoryInterface::class => ProductUnitRepository::class,
        ProductUnitServiceInterface::class => ProductUnitService::class,
        StockRepositoryInterface::class => StockRepository::class,
        StockServiceInterface::class => StockService::class,

        LoyaltyUserRepositoryInterface::class => LoyaltyUserRepository::class,
        ClaimRepositoryInterface::class => ClaimRepository::class,
        RedemptionRepositoryInterface::class => RedemptionRepository::class,
        PrizeRepositoryInterface::class => PrizeRepository::class,
        PrizeCategoryRepositoryInterface::class => PrizeCategoryRepository::class,
        PointsTransactionRepositoryInterface::class => PointsTransactionRepository::class,
        LoyaltyAuthServiceInterface::class => LoyaltyAuthService::class,
        LoyaltyProfileServiceInterface::class => LoyaltyProfileService::class,
        CustomerManagementServiceInterface::class => CustomerManagementService::class,
        ClaimServiceInterface::class => ClaimService::class,
        ClaimReviewServiceInterface::class => ClaimReviewService::class,
        RedemptionServiceInterface::class => RedemptionService::class,
        RedemptionReviewServiceInterface::class => RedemptionReviewService::class,
        PrizeServiceInterface::class => PrizeService::class,
        PrizeCategoryServiceInterface::class => PrizeCategoryService::class,
        PointsServiceInterface::class => PointsService::class,

        AdjustmentRequestRepositoryInterface::class => AdjustmentRequestRepository::class,
        AdjustmentRequestServiceInterface::class => AdjustmentRequestService::class,
        StockHistoryRepositoryInterface::class => StockHistoryRepository::class,
        StockHistoryServiceInterface::class => StockHistoryService::class,

        StockTransferRepositoryInterface::class => StockTransferRepository::class,
        StockTransferServiceInterface::class => StockTransferService::class,
        TemporaryStockRepositoryInterface::class => TemporaryStockRepository::class,
        TemporaryStockServiceInterface::class => TemporaryStockService::class,
        StockProductUnitRepositoryInterface::class => StockProductUnitRepository::class,
        StockProductUnitServiceInterface::class => StockProductUnitService::class,
        StockGroupingServiceInterface::class => StockGroupingService::class,
        StockRepackServiceInterface::class => StockRepackService::class,

        ReceiveOrderDetailRepositoryInterface::class => ReceiveOrderDetailRepository::class,

        ReceiveOrderRepositoryInterface::class => ReceiveOrderRepository::class,
        ReceiveOrderServiceInterface::class => ReceiveOrderService::class,
        ReceiveOrderDetailServiceInterface::class => ReceiveOrderDetailService::class,

        StockOpnameRepositoryInterface::class => StockOpnameRepository::class,
        StockOpnameServiceInterface::class => StockOpnameService::class,
        StockOpnameDetailServiceInterface::class => StockOpnameDetailService::class,

        DeliveryOrderRepositoryInterface::class => DeliveryOrderRepository::class,
        DeliveryOrderServiceInterface::class => DeliveryOrderService::class,
        SalesOrderRepositoryInterface::class => SalesOrderRepository::class,
        SalesOrderServiceInterface::class => SalesOrderService::class,
        DeliveryOrderDetailServiceInterface::class => DeliveryOrderDetailService::class,

        PaymentRepositoryInterface::class => PaymentRepository::class,
        PaymentServiceInterface::class => PaymentService::class,
        VoucherRepositoryInterface::class => VoucherRepository::class,
        VoucherServiceInterface::class => VoucherService::class,
        VoucherCategoryRepositoryInterface::class => VoucherCategoryRepository::class,
        VoucherCategoryServiceInterface::class => VoucherCategoryService::class,
        VoucherGenerateBatchRepositoryInterface::class => VoucherGenerateBatchRepository::class,
        VoucherGenerateBatchServiceInterface::class => VoucherGenerateBatchService::class,

        InvoiceNumberServiceInterface::class => InvoiceNumberService::class,
        InvoiceServiceInterface::class => InvoiceService::class,
        OrderServiceInterface::class => OrderService::class,
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Mail::extend('brevo', function () {
            return new BrevoApiTransport(
                config('services.brevo.key'),
                HttpClient::create()
            );
        });
    }
}
