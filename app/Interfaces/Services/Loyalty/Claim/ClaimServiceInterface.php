<?php

namespace App\Interfaces\Services\Loyalty\Claim;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\Claim;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface ClaimServiceInterface extends BaseServiceInterface
{
    public function submit(LoyaltyUser $user, mixed $invoiceNumber, UploadedFile $invoicePhoto, array $productPhotos): Claim;

    public function paginateForUser(string $userId, ?string $status, int $perPage): LengthAwarePaginator;

    public function findForUser(string $userId, string $id): ?Claim;
}
