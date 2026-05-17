<?php

namespace App\Http\Controllers\Api\Loyalty\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Loyalty\LoyaltyUserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request)
    {
        return new LoyaltyUserResource($request->user());
    }
}
