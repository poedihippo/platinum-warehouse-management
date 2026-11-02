<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Loyalty\PrizeCategory\StoreRequest;
use App\Http\Requests\Api\Loyalty\PrizeCategory\UpdateRequest;
use App\Http\Resources\Loyalty\AdminPrizeCategoryResource;
use App\Interfaces\Services\Loyalty\PrizeCategory\PrizeCategoryServiceInterface;
use App\Traits\Controllers\DeniesUnlessCan;
use Illuminate\Http\Request;

class PrizeCategoryManagementController extends BaseController
{
    use DeniesUnlessCan;

    private const PERMISSION = 'manage prizes';

    private const DENIED_MESSAGE = 'Anda tidak memiliki izin untuk mengelola kategori hadiah.';

    public function __construct(private PrizeCategoryServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/admin/loyalty/prize-categories
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        return AdminPrizeCategoryResource::collection($this->service->listWithPrizeCount());
    }

    /**
     * POST /api/admin/loyalty/prize-categories
     */
    public function store(StoreRequest $request)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $category = $this->service->create($request->validated());

        return (new AdminPrizeCategoryResource($category->loadCount('prizes')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /api/admin/loyalty/prize-categories/{prizeCategory}
     */
    public function update(UpdateRequest $request, string $prizeCategory)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $model = $this->service->findById($prizeCategory);

        if (! $model) {
            return response()->json(['message' => 'Kategori hadiah tidak ditemukan.'], 404);
        }

        $this->service->update($model, $request->validated());

        return new AdminPrizeCategoryResource($model->loadCount('prizes'));
    }

    /**
     * DELETE /api/admin/loyalty/prize-categories/{prizeCategory}
     */
    public function destroy(Request $request, string $prizeCategory)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $model = $this->service->findById($prizeCategory);

        if (! $model) {
            return response()->json(['message' => 'Kategori hadiah tidak ditemukan.'], 404);
        }

        $this->service->delete($model);

        return response()->json(null, 204);
    }
}
