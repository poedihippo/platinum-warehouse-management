<?php

namespace App\Http\Controllers\Api\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Loyalty\LoyaltyClaimStoreRequest;
use App\Http\Resources\Loyalty\ClaimResource;
use App\Models\Loyalty\Claim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClaimController extends Controller
{
    /**
     * POST /api/loyalty/claims — submit a claim.
     *
     * Storage layout (the claim ULID guarantees uniqueness, no counter):
     *   loyalty/claims/{claimId}/invoice.{ext}
     *   loyalty/claims/{claimId}/product_{position}.{ext}
     */
    public function store(LoyaltyClaimStoreRequest $request)
    {
        $user = $request->user();

        // Fraud rule §9.1: email must be verified before submitting.
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email Anda belum diverifikasi. Verifikasi email sebelum mengajukan klaim.',
            ], 403);
        }

        $invoiceNumber = $request->input('invoice_number');

        // Fraud rule §9.1: same user + invoice number can only be
        // submitted once (any status). The DB unique index is the hard
        // guarantee; this check produces a friendly localized message.
        $alreadyExists = Claim::where('loyalty_user_id', $user->getKey())
            ->where('invoice_number', $invoiceNumber)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'message' => 'Invoice ini sudah pernah Anda submit.',
            ], 422);
        }

        // Pre-generate the claim ULID so it can key the storage path.
        // Lowercase to match the existing ULID convention in this DB.
        $claimId = strtolower((string) Str::ulid());
        $baseDir = "loyalty/claims/{$claimId}";

        $invoiceFile = $request->file('invoice_photo');
        $invoicePath = $invoiceFile->storeAs(
            $baseDir,
            'invoice.' . $this->extensionOf($invoiceFile)
        );

        $productPhotoPaths = [];
        foreach (array_values($request->file('product_photos')) as $index => $file) {
            $position = $index + 1;
            $productPhotoPaths[$position] = $file->storeAs(
                $baseDir,
                "product_{$position}." . $this->extensionOf($file)
            );
        }

        try {
            $claim = DB::transaction(function () use (
                $claimId,
                $user,
                $invoiceNumber,
                $invoicePath,
                $productPhotoPaths
            ) {
                $claim = Claim::create([
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
            // Roll back orphaned uploads if the DB write failed.
            Storage::deleteDirectory($baseDir);
            throw $e;
        }

        return new ClaimResource($claim->load('photos'));
    }

    /**
     * GET /api/loyalty/claims — own claims, newest first, 15/page.
     */
    public function index(Request $request)
    {
        $claims = Claim::where('loyalty_user_id', $request->user()->getKey())
            ->orderByDesc('submitted_at')
            ->paginate(15);

        return ClaimResource::collection($claims);
    }

    /**
     * GET /api/loyalty/claims/{claim} — single own claim.
     * 404 (not 403) if not owned, so existence is not leaked.
     */
    public function show(Request $request, string $claim)
    {
        $model = Claim::with(['photos', 'lineItems'])
            ->where('loyalty_user_id', $request->user()->getKey())
            ->where('id', $claim)
            ->first();

        if (!$model) {
            return response()->json(['message' => 'Klaim tidak ditemukan.'], 404);
        }

        return new ClaimResource($model);
    }

    private function extensionOf($file): string
    {
        return strtolower(
            $file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg')
        );
    }
}
