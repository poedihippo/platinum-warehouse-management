<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payment\StoreRequest;
use App\Http\Resources\DefaultResource;
use App\Models\Payment;
use App\Models\SalesOrder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\QueryBuilder;

class PaymentController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // $this->middleware('permission:payment_access', ['only' => ['index', 'show']]);
        $this->middleware('permission:payment_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:payment_create', ['only' => 'store']);
        $this->middleware('permission:payment_edit', ['only' => 'update']);
        $this->middleware('permission:payment_delete', ['only' => 'destroy', 'forceDelete', 'restore']);
    }

    public function index()
    {
        $payments = QueryBuilder::for(Payment::class)
            ->allowedFilters([
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('sales_order_id'),
                'type'
            ])
            ->allowedIncludes([
                AllowedInclude::callback('user', function ($query) {
                    $query->select('id', 'name');
                }),
                'salesOrder'
            ])
            ->allowedSorts(['id', 'sales_order_id', 'user_id', 'type', 'created_at'])
            ->paginate($this->per_page);

        return DefaultResource::collection($payments);
    }

    public function show(Payment $payment)
    {
        return new DefaultResource($payment->load(['salesOrder', 'user' => fn ($q) => $q->select('id', 'name')]));
    }

    public function store(StoreRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            if ($request->is_full_payment == true) {
                $salesOrder = SalesOrder::where('id', $request->sales_order_id)->firstOrFail(['price']);
                $data['amount'] = $salesOrder->price;
            }

            $payment = Payment::create($data);
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    if ($file->isValid()) $payment->addMedia($file)->toMediaCollection('payments');
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage());
        }

        return new DefaultResource($payment);
    }

    public function update(Payment $payment, StoreRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            if ($request->is_full_payment == true) {
                $salesOrder = SalesOrder::where('id', $request->sales_order_id)->firstOrFail(['price']);
                $data['amount'] = $salesOrder->price;
            }

            $payment->update($data);
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    if ($file->isValid()) $payment->addMedia($file)->toMediaCollection('payments');
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage());
        }

        return (new DefaultResource($payment))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return $this->deletedResponse();
    }

    public function forceDelete(int $id)
    {
        $payment = Payment::withTrashed()->findOrFail($id);
        $payment->forceDelete();

        return $this->deletedResponse();
    }

    public function restore(int $id)
    {
        $payment = Payment::withTrashed()->findOrFail($id);
        $payment->restore();

        return new DefaultResource($payment);
    }
}
