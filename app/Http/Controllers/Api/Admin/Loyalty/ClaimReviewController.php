<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Loyalty\Claim\AddLineItemRequest;
use App\Http\Requests\Api\Loyalty\Claim\RejectRequest;
use App\Http\Requests\Api\Loyalty\Claim\UpdateLineItemRequest;
use App\Http\Resources\Loyalty\AdminClaimResource;
use App\Interfaces\Services\Loyalty\Claim\ClaimReviewServiceInterface;
use App\Traits\Controllers\ClampsLoyaltyPerPage;
use App\Traits\Controllers\DeniesUnlessCan;
use Illuminate\Http\Request;

class ClaimReviewController extends BaseController
{
    use ClampsLoyaltyPerPage;
    use DeniesUnlessCan;

    private const PERMISSION = 'review claims';

    private const DENIED_MESSAGE = 'Anda tidak memiliki izin untuk meninjau klaim.';

    public function __construct(private ClaimReviewServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/admin/loyalty/claims
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $claims = $this->service->paginate(
            $request->filled('status') && $request->input('status') !== 'all' ? $request->input('status') : null,
            $request->filled('q') ? $request->input('q') : null,
            $request->input('sort'),
            $this->loyaltyPerPage($request),
        );

        return AdminClaimResource::collection($claims);
    }

    /**
     * GET /api/admin/loyalty/claims/{claim}
     *
     * Includes duplicate_warnings: claims by other customers with the same invoice number.
     */
    public function show(Request $request, string $claim)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $model = $this->service->findForReview($claim);

        if (! $model) {
            return response()->json(['message' => 'Klaim tidak ditemukan.'], 404);
        }

        return new AdminClaimResource($model);
    }

    /**
     * POST /api/admin/loyalty/claims/{claim}/line-items
     */
    public function addLineItem(AddLineItemRequest $request, string $claim)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $lineItem = $this->service->addLineItem($claim, $request->input('product_unit_id'), $request->input('quantity'));

        return response()->json([
            'message' => 'Line item ditambahkan.',
            'data' => [
                'id' => $lineItem->id,
                'product_unit_id' => $lineItem->product_unit_id,
                'quantity' => (int) $lineItem->quantity,
            ],
        ], 201);
    }

    /**
     * PATCH /api/admin/loyalty/claims/{claim}/line-items/{lineItem}
     */
    public function updateLineItem(UpdateLineItemRequest $request, string $claim, string $lineItem)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $item = $this->service->updateLineItem($claim, $lineItem, $request->input('quantity'));

        return response()->json([
            'message' => 'Line item diperbarui.',
            'data' => [
                'id' => $item->id,
                'product_unit_id' => $item->product_unit_id,
                'quantity' => (int) $item->quantity,
                'points_awarded' => (int) $item->points_awarded,
            ],
        ]);
    }

    /**
     * DELETE /api/admin/loyalty/claims/{claim}/line-items/{lineItem}
     */
    public function removeLineItem(Request $request, string $claim, string $lineItem)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        $this->service->removeLineItem($claim, $lineItem);

        return response()->json(['message' => 'Line item dihapus.']);
    }

    /**
     * POST /api/admin/loyalty/claims/{claim}/approve
     */
    public function approve(Request $request, string $claim)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        return new AdminClaimResource($this->service->approve($claim, $request->user()->getKey()));
    }

    /**
     * POST /api/admin/loyalty/claims/{claim}/reject
     */
    public function reject(RejectRequest $request, string $claim)
    {
        if ($denied = $this->denyUnlessCan($request, self::PERMISSION, self::DENIED_MESSAGE)) {
            return $denied;
        }

        return new AdminClaimResource($this->service->reject($claim, $request->user()->getKey(), $request->input('reason')));
    }
}
