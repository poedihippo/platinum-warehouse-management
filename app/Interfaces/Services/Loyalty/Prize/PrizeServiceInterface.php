<?php

namespace App\Interfaces\Services\Loyalty\Prize;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\Prize;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface PrizeServiceInterface extends BaseServiceInterface
{
    public function catalog(?string $search, ?string $categoryId, ?string $sort, ?string $affordableForUserId, int $perPage): LengthAwarePaginator;

    public function findActive(string $id): ?Prize;

    public function paginateForAdmin(?bool $isActive, ?string $search, ?string $sort, int $perPage): LengthAwarePaginator;

    public function createPrize(array $attributes, ?UploadedFile $photo): Prize;

    public function updatePrize(Prize $prize, array $data, ?UploadedFile $photo): Prize;

    public function toggleActive(Prize $prize): Prize;
}
