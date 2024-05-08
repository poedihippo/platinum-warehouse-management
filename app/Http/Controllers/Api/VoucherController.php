<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Voucher\StoreRequest;
use App\Http\Resources\DefaultResource;
use App\Imports\VoucherImport;
use App\Models\Voucher;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class VoucherController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:voucher_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:voucher_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:voucher_create', ['only' => 'store']);
        $this->middleware('permission:voucher_edit', ['only' => 'update']);
        $this->middleware('permission:voucher_delete', ['only' => 'destroy','forceDelete','restore']);
        $this->middleware('permission:voucher_import', ['only' => 'import']);
    }

    public function index()
    {
        $vouchers = QueryBuilder::for(Voucher::class)
            ->allowedFilters([
                AllowedFilter::exact('voucher_generate_batch_id'),
                AllowedFilter::exact('voucher_category_id'),
                'code'
            ])
            ->allowedIncludes('category')
            ->allowedSorts(['id', 'voucher_category_id', 'name', 'code', 'created_at'])
            ->paginate($this->per_page);

        return DefaultResource::collection($vouchers);
    }

    public function show(Voucher $voucher)
    {
        return new DefaultResource($voucher->load('category'));
    }

    public function store(StoreRequest $request)
    {
        $voucher = Voucher::create($request->validated());

        return new DefaultResource($voucher);
    }

    public function update(Voucher $voucher, StoreRequest $request)
    {
        if ($voucher->is_used) {
            return $this->errorResponse(message: 'Cannot update used voucher. Voucher already used!', code: \Illuminate\Http\Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $voucher->update($request->validated());

        return (new DefaultResource($voucher))->response()->setStatusCode(\Illuminate\Http\Response::HTTP_ACCEPTED);
    }

    public function destroy(Voucher $voucher)
    {
        if ($voucher->is_used) {
            return $this->errorResponse(message: 'Cannot delete used voucher. Voucher already used!', code: \Illuminate\Http\Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $voucher->delete();
        return $this->deletedResponse();
    }

    public function forceDelete(int $id)
    {
        if ($voucher->is_used) {
            return $this->errorResponse(message: 'Cannot delete used voucher. Voucher already used!', code: \Illuminate\Http\Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $voucher = Voucher::withTrashed()->findOrFail($id);
        $voucher->forceDelete();

        return $this->deletedResponse();
    }

    public function restore(int $id)
    {
        $voucher = Voucher::withTrashed()->findOrFail($id);
        $voucher->restore();

        return new DefaultResource($voucher);
    }

    public function import(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'voucher_category_id' => 'required|exists:voucher_categories,id',
            'description' => 'nullable|string',
            'file' => 'required|mimes:xls,xlsx,csv',
        ]);

        $import = new VoucherImport($request->voucher_category_id, $request->description);
        $import->import($request->file('file'));

        return $this->createdResponse($import->getTotalInserted() . " data inserted successfully");
    }
}
