<?php

namespace App\Traits\Controllers;

use Illuminate\Http\Request;

trait ClampsLoyaltyPerPage
{
    protected function loyaltyPerPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return $perPage > 0 && $perPage <= $max ? $perPage : $default;
    }
}
