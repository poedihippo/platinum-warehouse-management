<?php

namespace App\Traits\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait DeniesUnlessCan
{
    protected function denyUnlessCan(Request $request, string $permission, string $message): ?JsonResponse
    {
        if ($request->user()?->can($permission)) {
            return null;
        }

        return response()->json([
            'message' => $message,
        ], 403);
    }
}
