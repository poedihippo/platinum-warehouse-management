<?php

namespace App\Interfaces\Services\Loyalty\Prize;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\Prize;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface PrizeServiceInterface extends BaseServiceInterface
{
    public function catalog(mixed $search, mixed $categoryId, mixed $sort, mixed $affordableForUserId, int $perPage): LengthAwarePaginator;

    public function findActive(mixed $id): ?Prize;

    public function paginateForAdmin(mixed $isActive, mixed $search, mixed $sort, int $perPage): LengthAwarePaginator;

    public function createPrize(mixed $attributes, ?UploadedFile $photo): Prize;

    public function updatePrize(Prize $prize, mixed $data, ?UploadedFile $photo): Prize;

    public function toggleActive(Prize $prize): Prize;
}
