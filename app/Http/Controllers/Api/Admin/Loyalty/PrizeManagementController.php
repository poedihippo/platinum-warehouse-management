<?php

namespace App\Http\Controllers\Api\Admin\Loyalty;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Loyalty\Admin\StorePrizeRequest;
use App\Http\Requests\Api\Loyalty\Admin\UpdatePrizeRequest;
use App\Http\Resources\Loyalty\AdminPrizeResource;
use App\Models\Loyalty\Prize;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrizeManagementController extends Controller
{
    private const PERMISSION = 'manage prizes';

    /**
     * GET /api/admin/loyalty/prizes
     * Includes inactive prizes by default. Newest first.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $query = Prize::withCount('redemptions')->orderByDesc('created_at');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        return AdminPrizeResource::collection($query->paginate($perPage));
    }

    /**
     * POST /api/admin/loyalty/prizes
     */
    public function store(StorePrizeRequest $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        // Pre-generate the ULID so it can key the S3 photo path.
        $prizeId = strtolower((string) Str::ulid());

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $this->storePhoto($request->file('photo'), $prizeId);
        }

        // Set the key directly (not via mass-assignment) so it matches the
        // ULID used for the photo path; HasUlids leaves a preset id alone.
        $prize = new Prize([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'points_cost' => (int) $request->input('points_cost'),
            'stock' => (int) $request->input('stock'),
            'is_active' => $request->boolean('is_active', true),
            'photo_path' => $photoPath,
        ]);
        $prize->id = $prizeId;
        $prize->save();

        return (new AdminPrizeResource($prize->loadCount('redemptions')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /api/admin/loyalty/prizes/{prize}
     */
    public function update(UpdatePrizeRequest $request, string $prize)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = Prize::find($prize);
        if (!$model) {
            return response()->json(['message' => 'Hadiah tidak ditemukan.'], 404);
        }

        $data = $request->only(['name', 'description', 'points_cost', 'stock', 'is_active']);

        if ($request->hasFile('photo')) {
            // Replace the existing photo on S3 (delete old, upload new).
            if ($model->photo_path) {
                Storage::disk('s3')->delete($model->photo_path);
            }
            $data['photo_path'] = $this->storePhoto($request->file('photo'), $model->id);
        }

        $model->update($data);

        return new AdminPrizeResource($model->loadCount('redemptions'));
    }

    /**
     * PATCH /api/admin/loyalty/prizes/{prize}/toggle-active
     */
    public function toggleActive(Request $request, string $prize)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $model = Prize::find($prize);
        if (!$model) {
            return response()->json(['message' => 'Hadiah tidak ditemukan.'], 404);
        }

        $model->update(['is_active' => !$model->is_active]);

        return new AdminPrizeResource($model->loadCount('redemptions'));
    }

    private function storePhoto(UploadedFile $file, string $prizeId): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));

        return $file->storeAs("loyalty/prizes/{$prizeId}", "main.{$ext}", 's3');
    }

    /**
     * Returns a 403 JSON response when the admin lacks the prize-management
     * permission, else null. Mirrors ProductUnitPointsController.
     */
    private function denyUnlessAuthorized(Request $request)
    {
        if ($request->user()?->can(self::PERMISSION)) {
            return null;
        }

        return response()->json([
            'message' => 'Anda tidak memiliki izin untuk mengelola hadiah.',
        ], 403);
    }
}
