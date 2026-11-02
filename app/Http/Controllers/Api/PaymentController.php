<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Payment\StoreRequest;
use App\Http\Resources\DefaultResource;
use App\Interfaces\Services\Payment\PaymentServiceInterface;
use Illuminate\Http\Response;

class PaymentController extends BaseController
{
    public function __construct(private PaymentServiceInterface $service)
    {
        parent::__construct();
        $this->middleware('permission:payment_read', ['only' => ['index', 'show']]);
        $this->middleware('permission:payment_create', ['only' => 'store']);
        $this->middleware('permission:payment_edit', ['only' => 'update']);
        $this->middleware('permission:payment_delete', ['only' => 'destroy', 'forceDelete', 'restore']);
    }

    public function index()
    {
        return DefaultResource::collection($this->service->paginateIndex($this->per_page));
    }

    public function show($id)
    {
        $payment = $this->service->findTenanted($id);

        return new DefaultResource($payment->load(['salesOrder', 'user' => fn ($q) => $q->select('id', 'name')]));
    }

    public function store(StoreRequest $request)
    {
        $payment = $this->service->store(
            $request->validated(),
            $request->is_full_payment,
            $request->sales_order_id,
            $request->hasFile('files') ? $request->file('files') : null,
        );

        return new DefaultResource($payment);
    }

    public function update($id, StoreRequest $request)
    {
        $payment = $this->service->updatePayment(
            $this->service->findTenanted($id),
            $request->validated(),
            $request->is_full_payment,
            $request->sales_order_id,
            $request->hasFile('files') ? $request->file('files') : null,
        );

        return (new DefaultResource($payment))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy($id)
    {
        $this->service->delete($this->service->findTenanted($id));

        return $this->deletedResponse();
    }

    public function forceDelete($id)
    {
        $this->service->forceDelete($this->service->findTenantedWithTrashed($id));

        return $this->deletedResponse();
    }

    public function restore($id)
    {
        $payment = $this->service->findTenantedWithTrashed($id);
        $this->service->restore($payment);

        return new DefaultResource($payment);
    }
}
