<?php

namespace App\Pagination;

use Illuminate\Pagination\LengthAwarePaginator as BaseLengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Laravel 12 added a "page" key to every entry of the paginator "links"
 * array. The BEJO and verify frontends read the Laravel 10 shape, so the
 * links keep exactly the url / label / active keys they had before.
 */
class LengthAwarePaginator extends BaseLengthAwarePaginator
{
    public function linkCollection(): Collection
    {
        return parent::linkCollection()->map(fn (array $link) => array_diff_key($link, ['page' => true]));
    }
}
