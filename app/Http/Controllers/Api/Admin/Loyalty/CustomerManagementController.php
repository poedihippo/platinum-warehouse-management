<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Loyalty\AdminCustomerListResource;
use App\Http\Resources\Loyalty\AdminCustomerResource;
use App\Interfaces\Services\Loyalty\LoyaltyUser\CustomerManagementServiceInterface;
use App\Traits\Controllers\ClampsLoyaltyPerPage;
use App\Traits\Controllers\DeniesUnlessCan;
use Illuminate\Http\Request;

class CustomerManagementController extends BaseController
{
    use ClampsLoyaltyPerPage;
    use DeniesUnlessCan;

    private const PERMISSION = 'manage customers';

    private const DENIED_MESSAGE = 'Anda tidak memiliki izin untuk mengelola pelanggan.';

    public function __construct(private CustomerManagementServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/admin/loyalty/customers
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $customers = $this->service->paginate(
            $request->filled('q') ? $request->input('q') : null,
            $request->filled('is_active') ? $request->boolean('is_active') : null,
            $request->input('sort'),
            $this->loyaltyPerPage($request),
        );

        return AdminCustomerListResource::collection($customers);
    }

    /**
     * GET /api/admin/loyalty/customers/{loyaltyUser}
     */
    public function show(Request $request, string $loyaltyUser)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $model = $this->service->findWithBalance($loyaltyUser);

        if (! $model) {
            return response()->json(['message' => 'Pelanggan tidak ditemukan.'], 404);
        }

        return new AdminCustomerResource($model);
    }

    /**
     * POST /api/admin/loyalty/customers/{loyaltyUser}/adjust-points
     *
     * The 404 check runs before validation.
     */
    public function adjustPoints(Request $request, string $loyaltyUser)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $model = $this->service->findById($loyaltyUser);

        if (! $model) {
            return response()->json(['message' => 'Pelanggan tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $model = $this->service->adjustPoints($model, (int) $validated['points'], $validated['reason'], $request->user()->getKey());

        return new AdminCustomerResource($model);
    }

    /**
     * PATCH /api/admin/loyalty/customers/{loyaltyUser}/toggle-active
     */
    public function toggleActive(Request $request, string $loyaltyUser)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $model = $this->service->findById($loyaltyUser);

        if (! $model) {
            return response()->json(['message' => 'Pelanggan tidak ditemukan.'], 404);
        }

        return new AdminCustomerResource($this->service->toggleActive($model));
    }
}
