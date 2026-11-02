<?php

namespace App\Http\Services\Loyalty\Prize;

use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Loyalty\PointsTransaction\PointsTransactionRepositoryInterface;
use App\Interfaces\Repositories\Loyalty\Prize\PrizeRepositoryInterface;
use App\Interfaces\Services\Loyalty\Prize\PrizeServiceInterface;
use App\Models\Loyalty\Prize;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrizeService extends BaseService implements PrizeServiceInterface
{
    public function __construct(
        protected PrizeRepositoryInterface $repository,
        protected PointsTransactionRepositoryInterface $pointsRepository,
    ) {
        parent::__construct($repository);
    }

    public function catalog(?string $search, ?string $categoryId, ?string $sort, ?string $affordableForUserId, int $perPage): LengthAwarePaginator
    {
        $maxPointsCost = $affordableForUserId !== null ? $this->pointsRepository->balanceFor($affordableForUserId) : null;

        return $this->repository->paginateCatalog($search, $categoryId, $sort, $maxPointsCost, $perPage);
    }

    public function findActive(string $id): ?Prize
    {
        return $this->repository->findActive($id);
    }

    public function paginateForAdmin(?bool $isActive, ?string $search, ?string $sort, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateForAdmin($isActive, $search, $sort, $perPage);
    }

    public function createPrize(array $attributes, ?UploadedFile $photo): Prize
    {
        $prizeId = strtolower((string) Str::ulid());

        $photoPath = null;
        if ($photo) {
            $photoPath = $this->storePhoto($photo, $prizeId);
        }

        $prize = new Prize([...$attributes, 'photo_path' => $photoPath]);
        $prize->id = $prizeId;
        $prize->save();

        return $prize->load('category')->loadCount('redemptions');
    }

    public function updatePrize(Prize $prize, array $data, ?UploadedFile $photo): Prize
    {
        if ($photo) {
            if ($prize->photo_path) {
                Storage::disk('s3')->delete($prize->photo_path);
            }
            $data['photo_path'] = $this->storePhoto($photo, $prize->id);
        }

        $prize->update($data);

        return $prize->load('category')->loadCount('redemptions');
    }

    public function toggleActive(Prize $prize): Prize
    {
        $prize->update(['is_active' => ! $prize->is_active]);

        return $prize->load('category')->loadCount('redemptions');
    }

    private function storePhoto(UploadedFile $file, string $prizeId): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));

        return $file->storeAs("loyalty/prizes/{$prizeId}", "main.{$ext}", 's3');
    }
}
