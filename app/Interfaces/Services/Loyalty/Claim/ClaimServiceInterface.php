<?php

namespace App\Interfaces\Services\Loyalty\Claim;

use App\Interfaces\Services\BaseServiceInterface;
use App\Models\Loyalty\Claim;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface ClaimServiceInterface extends BaseServiceInterface
{
    public function submit(LoyaltyUser $user, mixed $invoiceNumber, UploadedFile $invoicePhoto, mixed $productPhotos): Claim;

    public function paginateForUser(mixed $userId, mixed $status, int $perPage): LengthAwarePaginator;

    public function findForUser(mixed $userId, mixed $id): ?Claim;
}
