<?php

namespace App\Providers;

use App\Http\Repositories\DocumentCounter\DocumentCounterRepository;
use App\Http\Repositories\Permission\PermissionRepository;
use App\Http\Repositories\Product\ProductRepository;
use App\Http\Repositories\ProductBrand\ProductBrandRepository;
use App\Http\Repositories\ProductCategory\ProductCategoryRepository;
use App\Http\Repositories\ProductUnitBlacklist\ProductUnitBlacklistRepository;
use App\Http\Repositories\Role\RoleRepository;
use App\Http\Repositories\Setting\SettingRepository;
use App\Http\Repositories\Supplier\SupplierRepository;
use App\Http\Repositories\Uom\UomRepository;
use App\Http\Repositories\User\UserRepository;
use App\Http\Repositories\UserDiscount\UserDiscountRepository;
use App\Http\Repositories\Warehouse\WarehouseRepository;
use App\Http\Services\Auth\AuthService;
use App\Http\Services\DocumentCounter\DocumentCounterService;
use App\Http\Services\DocumentCounter\NumberingService;
use App\Http\Services\Export\ExportService;
use App\Http\Services\Permission\PermissionService;
use App\Http\Services\Product\ProductService;
use App\Http\Services\ProductBrand\ProductBrandService;
use App\Http\Services\ProductCategory\ProductCategoryService;
use App\Http\Services\ProductUnitBlacklist\ProductUnitBlacklistService;
use App\Http\Services\Role\RoleService;
use App\Http\Services\Setting\SettingService;
use App\Http\Services\Supplier\SupplierService;
use App\Http\Services\Uom\UomService;
use App\Http\Services\User\UserService;
use App\Http\Services\UserDiscount\UserDiscountService;
use App\Http\Services\Warehouse\WarehouseService;
use App\Interfaces\Repositories\DocumentCounter\DocumentCounterRepositoryInterface;
use App\Interfaces\Repositories\Permission\PermissionRepositoryInterface;
use App\Interfaces\Repositories\Product\ProductRepositoryInterface;
use App\Interfaces\Repositories\ProductBrand\ProductBrandRepositoryInterface;
use App\Interfaces\Repositories\ProductCategory\ProductCategoryRepositoryInterface;
use App\Interfaces\Repositories\ProductUnitBlacklist\ProductUnitBlacklistRepositoryInterface;
use App\Interfaces\Repositories\Role\RoleRepositoryInterface;
use App\Interfaces\Repositories\Setting\SettingRepositoryInterface;
use App\Interfaces\Repositories\Supplier\SupplierRepositoryInterface;
use App\Interfaces\Repositories\Uom\UomRepositoryInterface;
use App\Interfaces\Repositories\User\UserRepositoryInterface;
use App\Interfaces\Repositories\UserDiscount\UserDiscountRepositoryInterface;
use App\Interfaces\Repositories\Warehouse\WarehouseRepositoryInterface;
use App\Interfaces\Services\Auth\AuthServiceInterface;
use App\Interfaces\Services\DocumentCounter\DocumentCounterServiceInterface;
use App\Interfaces\Services\DocumentCounter\NumberingServiceInterface;
use App\Interfaces\Services\Export\ExportServiceInterface;
use App\Interfaces\Services\Permission\PermissionServiceInterface;
use App\Interfaces\Services\Product\ProductServiceInterface;
use App\Interfaces\Services\ProductBrand\ProductBrandServiceInterface;
use App\Interfaces\Services\ProductCategory\ProductCategoryServiceInterface;
use App\Interfaces\Services\ProductUnitBlacklist\ProductUnitBlacklistServiceInterface;
use App\Interfaces\Services\Role\RoleServiceInterface;
use App\Interfaces\Services\Setting\SettingServiceInterface;
use App\Interfaces\Services\Supplier\SupplierServiceInterface;
use App\Interfaces\Services\Uom\UomServiceInterface;
use App\Interfaces\Services\User\UserServiceInterface;
use App\Interfaces\Services\UserDiscount\UserDiscountServiceInterface;
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
