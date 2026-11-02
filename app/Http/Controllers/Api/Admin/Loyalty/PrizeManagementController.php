<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Loyalty\Prize\StoreRequest;
use App\Http\Requests\Api\Loyalty\Prize\UpdateRequest;
use App\Http\Resources\Loyalty\AdminPrizeResource;
use App\Interfaces\Services\Loyalty\Prize\PrizeServiceInterface;
use App\Traits\Controllers\ClampsLoyaltyPerPage;
use App\Traits\Controllers\DeniesUnlessCan;
use Illuminate\Http\Request;

class PrizeManagementController extends BaseController
{
    use ClampsLoyaltyPerPage;
    use DeniesUnlessCan;

    private const PERMISSION = 'manage prizes';

    private const DENIED_MESSAGE = 'Anda tidak memiliki izin untuk mengelola hadiah.';

    public function __construct(private PrizeServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/admin/loyalty/prizes
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $prizes = $this->service->paginateForAdmin(
            $request->filled('is_active') ? $request->boolean('is_active') : null,
            $request->filled('q') ? $request->input('q') : null,
            $request->input('sort'),
            $this->loyaltyPerPage($request),
        );

        return AdminPrizeResource::collection($prizes);
    }

    /**
     * GET /api/admin/loyalty/prizes/{prize}
     */
    public function show(Request $request, string $prize)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $model = $this->service->findById($prize);

        if (! $model) {
            return response()->json(['message' => 'Hadiah tidak ditemukan.'], 404);
        }

        return new AdminPrizeResource($model->load('category')->loadCount('redemptions'));
    }

    /**
     * POST /api/admin/loyalty/prizes
     */
    public function store(StoreRequest $request)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $prize = $this->service->createPrize([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'points_cost' => (int) $request->input('points_cost'),
            'stock' => (int) $request->input('stock'),
            'is_active' => $request->boolean('is_active', true),
            'product_url' => $request->input('product_url'),
            'prize_category_id' => $request->input('prize_category_id'),
        ], $request->hasFile('photo') ? $request->file('photo') : null);

        return (new AdminPrizeResource($prize))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /api/admin/loyalty/prizes/{prize}
     */
    public function update(UpdateRequest $request, string $prize)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $model = $this->service->findById($prize);

        if (! $model) {
            return response()->json(['message' => 'Hadiah tidak ditemukan.'], 404);
        }

        $model = $this->service->updatePrize(
            $model,
            $request->only(['name', 'description', 'points_cost', 'stock', 'is_active', 'product_url', 'prize_category_id']),
            $request->hasFile('photo') ? $request->file('photo') : null,
        );

        return new AdminPrizeResource($model);
    }

    /**
     * PATCH /api/admin/loyalty/prizes/{prize}/toggle-active
     */
    public function toggleActive(Request $request, string $prize)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $model = $this->service->findById($prize);

        if (! $model) {
            return response()->json(['message' => 'Hadiah tidak ditemukan.'], 404);
        }

        return new AdminPrizeResource($this->service->toggleActive($model));
    }
}
