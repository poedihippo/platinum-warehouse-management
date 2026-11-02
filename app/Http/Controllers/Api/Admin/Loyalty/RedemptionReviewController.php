<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Loyalty\Redemption\RejectRequest;
use App\Http\Requests\Api\Loyalty\Redemption\ShipRequest;
use App\Http\Resources\Loyalty\AdminRedemptionResource;
use App\Interfaces\Services\Loyalty\Redemption\RedemptionReviewServiceInterface;
use App\Traits\Controllers\ClampsLoyaltyPerPage;
use App\Traits\Controllers\DeniesUnlessCan;
use Illuminate\Http\Request;

class RedemptionReviewController extends BaseController
{
    use ClampsLoyaltyPerPage;
    use DeniesUnlessCan;

    private const PERMISSION = 'review redemptions';

    private const DENIED_MESSAGE = 'Anda tidak memiliki izin untuk meninjau penukaran.';

    public function __construct(private RedemptionReviewServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/admin/loyalty/redemptions
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $redemptions = $this->service->paginate(
            $request->filled('status') && $request->input('status') !== 'all' ? $request->input('status') : null,
            $request->filled('q') ? $request->input('q') : null,
            $request->input('sort'),
            $this->loyaltyPerPage($request),
        );

        return AdminRedemptionResource::collection($redemptions);
    }

    /**
     * GET /api/admin/loyalty/redemptions/{redemption}
     */
    public function show(Request $request, string $redemption)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $model = $this->service->findForReview($redemption);

        if (! $model) {
            return response()->json(['message' => 'Penukaran tidak ditemukan.'], 404);
        }

        return new AdminRedemptionResource($model);
    }

    /**
     * POST /api/admin/loyalty/redemptions/{redemption}/approve
     */
    public function approve(Request $request, string $redemption)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        return new AdminRedemptionResource($this->service->approve($redemption, $request->user()->getKey()));
    }

    /**
     * POST /api/admin/loyalty/redemptions/{redemption}/reject
     */
    public function reject(RejectRequest $request, string $redemption)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        return new AdminRedemptionResource(
            $this->service->reject($redemption, $request->user()->getKey(), $request->input('reason'))
        );
    }

    /**
     * POST /api/admin/loyalty/redemptions/{redemption}/ship
     */
    public function ship(ShipRequest $request, string $redemption)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        return new AdminRedemptionResource($this->service->ship(
            $redemption,
            $request->user()->getKey(),
            $request->input('tracking_number'),
            $request->input('shipping_carrier'),
        ));
    }

    /**
     * POST /api/admin/loyalty/redemptions/{redemption}/deliver
     */
    public function deliver(Request $request, string $redemption)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        return new AdminRedemptionResource($this->service->deliver($redemption, $request->user()->getKey()));
    }
}
