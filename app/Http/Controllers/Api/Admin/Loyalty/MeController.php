<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Api\BaseController;
use App\Interfaces\Services\User\UserServiceInterface;
use Illuminate\Http\Request;

class MeController extends BaseController
{
    public function __construct(private UserServiceInterface $service)
    {
        parent::__construct();
    }

    /**
     * GET /api/admin/loyalty/me
     *
     * Loyalty permissions of the current staff user as a flat list.
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'data' => $this->service->loyaltyAdminProfile($request->user()),
        ]);
    }
}
