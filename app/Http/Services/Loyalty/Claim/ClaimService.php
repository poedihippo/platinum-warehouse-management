<?php

namespace App\Http\Services\Loyalty\Claim;

use App\Exceptions\ApiResponseException;
use App\Http\Services\BaseService;
use App\Interfaces\Repositories\Loyalty\Claim\ClaimRepositoryInterface;
use App\Interfaces\Services\Loyalty\Claim\ClaimServiceInterface;
use App\Models\Loyalty\Claim;
use App\Models\Loyalty\LoyaltyUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClaimService extends BaseService implements ClaimServiceInterface
{
    public function __construct(protected ClaimRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Photos are written before the transaction, under a directory named
     * after the pre-generated claim id; a failed insert removes them again.
     */
    public function submit(LoyaltyUser $user, mixed $invoiceNumber, UploadedFile $invoicePhoto, array $productPhotos): Claim
    {
        if (! $user->hasVerifiedEmail()) {
            throw new ApiResponseException('Email Anda belum diverifikasi. Verifikasi email sebelum mengajukan klaim.', 403);
        }

        if ($this->repository->existsForUserInvoice($user->getKey(), $invoiceNumber)) {
            throw new ApiResponseException('Invoice ini sudah pernah Anda submit.', 422);
        }

        $claimId = strtolower((string) Str::ulid());
        $baseDir = "loyalty/claims/{$claimId}";

        $invoicePath = $invoicePhoto->storeAs(
            $baseDir,
            'invoice.'.$this->extensionOf($invoicePhoto)
        );

        $productPhotoPaths = [];
        foreach (array_values($productPhotos) as $index => $file) {
            $position = $index + 1;
            $productPhotoPaths[$position] = $file->storeAs(
                $baseDir,
                "product_{$position}.".$this->extensionOf($file)
            );
        }

        try {
            $claim = DB::transaction(function () use ($claimId, $user, $invoiceNumber, $invoicePath, $productPhotoPaths) {
                $claim = $this->repository->create([
                    'id' => $claimId,
                    'loyalty_user_id' => $user->getKey(),
                    'invoice_number' => $invoiceNumber,
                    'invoice_photo_path' => $invoicePath,
                    'status' => 'pending',
                    'submitted_at' => now(),
                    'total_points' => 0,
                ]);

                foreach ($productPhotoPaths as $position => $path) {
                    $claim->photos()->create([
                        'photo_path' => $path,
                        'position' => $position,
                    ]);
                }

                return $claim;
            });
        } catch (\Throwable $e) {
            Storage::deleteDirectory($baseDir);

            throw $e;
        }

        return $claim->load('photos');
    }

    public function paginateForUser(string $userId, ?string $status, int $perPage): LengthAwarePaginator
    {
        return $this->repository->paginateForUser($userId, $status, $perPage);
    }

    public function findForUser(string $userId, string $id): ?Claim
    {
        return $this->repository->findForUser($userId, $id);
    }

    private function extensionOf($file): string
    {
        return strtolower(
            $file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg')
        );
    }
}
