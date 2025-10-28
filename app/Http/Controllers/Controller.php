<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected int $per_page;

    public function __construct()
    {
        $perPage = (int) request()->per_page;
        $this->per_page = $perPage > 0 ? $perPage : 15;
    }

    public function createdResponse(?string $message = null, int $code = 201)
    {
        return response()->json(['message' => $message ?? 'Data created successfully'], $code);
    }

    public function updatedResponse(?string $message = null, int $code = 200)
    {
        return response()->json(['message' => $message ?? 'Data updated successfully'], $code);
    }

    public function deletedResponse()
    {
        return response()->json(['message' => 'Data deleted successfully']);
    }

    public function errorResponse(string $message, array $data = [], int $code = 500)
    {
        return response()->json(['message' => $message, ...$data], $code);
    }
}
