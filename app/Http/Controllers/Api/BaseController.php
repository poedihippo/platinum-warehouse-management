<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{
    protected int $per_page;

    public function __construct()
    {
        $perPage = (int) request()->per_page;
        $this->per_page = $perPage > 0 ? $perPage : 15;
    }

    public function createdResponse(?string $message = null, int $code = 201): JsonResponse
    {
        return response()->json(['message' => $message ?? 'Data created successfully'], $code);
    }

    public function updatedResponse(?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json(['message' => $message ?? 'Data updated successfully'], $code);
    }

    public function deletedResponse(): JsonResponse
    {
        return response()->json(['message' => 'Data deleted successfully']);
    }

    public function errorResponse(string $message, array $data = [], int $code = 500): JsonResponse
    {
        return response()->json(['message' => $message, ...$data], $code);
    }
}
