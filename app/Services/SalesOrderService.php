<?php

namespace App\Services;

use App\Http\Resources\SalesOrderResource;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\Warehouse;
use App\Pipes\Order\CalculateAdditionalDiscount;
use App\Pipes\Order\CalculateAdditionalFees;
use App\Pipes\Order\CalculateAutoDiscount;
use App\Pipes\Order\CalculateVoucher;
use App\Pipes\Order\CheckExpectedOrderPrice;
use App\Pipes\Order\FillOrderAttributes;
use App\Pipes\Order\FillOrderRecords;
use App\Pipes\Order\MakeOrderDetails;
use App\Pipes\Order\SaveOrder;
use App\Pipes\Order\UpdateOrder;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Crypt;
use Spatie\QueryBuilder\AllowedFilter;

class SalesOrderService
{
    /**
     * validation total price between BE calculation with FE calculation
     *
     * @param int|float $totalPrice total price from FE calculation
     * @param array $items SO items data
     *
     * @return bool
     */
    public static function validateTotalPrice(int $totalPrice, int $shipmentFee = 0, array $items): bool
    {
        $cekTotalPrice = 0;
        $pricePerItem = 0;
        foreach ($items as $item) {
            $pricePerItem = $item['unit_price'] * $item['qty'];
            $discount = $pricePerItem * ($item['discount'] / 100);
            $pricePerItem = $pricePerItem - $discount;
            if ($item['tax'] == 1) {
                $tax = $pricePerItem * 0.11;
                $pricePerItem = $pricePerItem + $tax;
            }
            $cekTotalPrice += $pricePerItem;
        }

        $cekTotalPrice += $shipmentFee;

        if ($cekTotalPrice != $totalPrice) return false;
        return true;
    }

    /**
     * count fulfilled_qty in sales_order_details
     *
     * @param SalesOrderDetail $salesOrderDetail
     *
     * @return void
     */
    public static function countFulfilledQty(SalesOrderDetail $salesOrderDetail): void
    {
        $salesOrderDetail->refresh();

        $salesOrderDetail->update([
            'fulfilled_qty' => $salesOrderDetail->salesOrderItems()->where('is_parent', 0)->count()
        ]);
    }

    /**
     * Creates a new sales order.
     *
     * @param SalesOrder $salesOrder The sales order object.
     * @param bool $isPerview (optional) Flag indicating whether the order is a preview. Default is false.
     * @return SalesOrder The created sales order.
     */
    public static function createOrder(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder
    {
        $pipes = [
            FillOrderAttributes::class,
            FillOrderRecords::class,
            MakeOrderDetails::class,
            CalculateAutoDiscount::class,
            CalculateVoucher::class,
            CalculateAdditionalDiscount::class,
            CalculateAdditionalFees::class,
            CheckExpectedOrderPrice::class,
        ];

        if (!$isPerview) $pipes[] = SaveOrder::class;

        return app(Pipeline::class)
            ->send($salesOrder)
            ->through($pipes)
            ->thenReturn();
    }

    /**
     * Updates a sales order.
     *
     * @param SalesOrder $salesOrder The sales order object to be updated.
     * @param bool $isPerview (optional) Flag indicating whether the order is a preview. Default is false.
     * @return SalesOrder The updated sales order.
     */
    public static function updateOrder(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder
    {
        $pipes = [
            FillOrderAttributes::class,
            FillOrderRecords::class,
            MakeOrderDetails::class,
            CalculateAutoDiscount::class,
            CalculateVoucher::class,
            CalculateAdditionalDiscount::class,
            CalculateAdditionalFees::class,
            CheckExpectedOrderPrice::class,
        ];

        if (!$isPerview) $pipes[] = UpdateOrder::class;

        return app(Pipeline::class)
            ->send($salesOrder)
            ->through($pipes)
            ->thenReturn();
    }

    /**
     * Updates a sales order.
     *
     * @param SalesOrder $salesOrder The sales order object to be updated.
     * @param bool $isPerview (optional) Flag indicating whether the order is a preview. Default is false.
     * @return SalesOrder The updated sales order.
     */
    public static function convertOrderToSO(SalesOrder $salesOrder, bool $isPerview = false): SalesOrder
    {
        $pipes = [
            FillOrderAttributes::class,
            FillOrderRecords::class,
            MakeOrderDetails::class,
            CalculateAutoDiscount::class,
            CalculateVoucher::class,
            CalculateAdditionalDiscount::class,
            CalculateAdditionalFees::class,
            CheckExpectedOrderPrice::class,
        ];

        if (!$isPerview) $pipes[] = UpdateOrder::class;

        return app(Pipeline::class)
            ->send($salesOrder)
            ->through($pipes)
            ->thenReturn();
    }

    public static function index(int $perPage, ?callable $query = null)
    {
        $salesOrders = \Spatie\QueryBuilder\QueryBuilder::for(
            SalesOrder::tenanted()->withCount('details')->when($query, $query)
        )
            ->allowedFilters([
                'invoice_no', 'is_invoice',
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('reseller_id'),
                AllowedFilter::exact('spg_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::scope('has_sales_order', 'hasSalesOrder'),
                AllowedFilter::scope('has_delivery_order', 'detailsHasDO'),
                AllowedFilter::scope('start_date'),
                AllowedFilter::scope('end_date'),
                AllowedFilter::callback('search', function ($q, $value) {
                    $q->where('invoice_no', 'like', '%' . $value . '%')
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', '%' . $value . '%'))
                        ->orWhereHas('reseller', fn ($q) => $q->where('name', 'like', '%' . $value . '%'))
                        ->orWhereHas('spg', fn ($q) => $q->where('name', 'like', '%' . $value . '%'));
                })
            ])
            ->allowedSorts(['id', 'invoice_no', 'user_id', 'reseller_id', 'warehouse_id', 'created_at'])
            ->allowedIncludes(['details', 'warehouse', 'user', 'payments', \Spatie\QueryBuilder\AllowedInclude::callback('voucher', function ($q) {
                $q->with('category');
            }),])
            ->paginate($perPage);

        return SalesOrderResource::collection($salesOrders);
    }

    public static function show(int $id, ?callable $query = null)
    {
        $salesOrder = SalesOrder::when($query, $query)->findTenanted($id);
        return $salesOrder->load([
            'voucher.category', 'payments', 'warehouse',
            'details' => fn ($q) => $q->with(['warehouse', 'packaging']),
            'user' => fn ($q) => $q->select('id', 'name', 'type'),
            'reseller' => fn ($q) => $q->select('id', 'name', 'type', 'type', 'email', 'phone', 'address'),
        ])->loadCount('details');
    }

    public static function print(int|string $id, string $type = 'print', ?callable $query = null)
    {
        if ($type == 'print') {
            $salesOrder = SalesOrder::when($query, $query)->findTenanted($id);
            $view = 'pdf.salesOrders.salesOrder';
        } else {
            $salesOrder = SalesOrder::when($query, $query)->find($id);
            if (!$salesOrder) return redirect()->away('https://platinumadisentosa.com');
            $view = 'pdf.salesOrders.salesOrderInvoice';
        }

        $salesOrder->load([
            'reseller',
            'details' => fn ($q) => $q->with('productUnit.product'),
        ])->loadSum('payments', 'amount');

        $salesOrderDetails = $salesOrder->details->chunk(10);

        $lastOrderDetailsKey = $salesOrderDetails->keys()->last();
        $maxProductsBlackSpace = 10;

        $spellTotalPrice = \NumberToWords\NumberToWords::transformNumber('en', $salesOrder->price);
        $bankTransferInfo = \App\Services\SettingService::bankTransferInfo();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::setPaper('a4')->loadView($view, ['salesOrder' => $salesOrder, 'salesOrderDetails' => $salesOrderDetails, 'maxProductsBlackSpace' => $maxProductsBlackSpace, 'lastOrderDetailsKey' => $lastOrderDetailsKey, 'spellTotalPrice' => $spellTotalPrice, 'bankTransferInfo' => $bankTransferInfo]);

        return $pdf->setPaper('a4', 'portrait')->download('sales-order-' . $salesOrder->invoice_no . '.pdf');
    }

    public static function exportXml(int $id, ?callable $query = null)
    {
        $salesOrder = SalesOrder::when($query, $query)->findTenanted($id);
        $salesOrder->load(['reseller', 'details' => fn ($q) => $q->with('packaging', 'productUnit')]);
        return response(view('xml.salesOrders.salesOrder')->with(compact('salesOrder')), 200, [
            'Content-Type' => 'application/xml',
            // use your required mime type
            'Content-Disposition' => 'attachment; filename="Sales Order ' . $salesOrder->invoice_no . '.xml"',
        ]);
    }

    public static function getWhatsappUrl(SalesOrder $salesOrder, ?string $idHash = null)
    {
        $warehouseName = $salesOrder->warehouse?->company_name ? $salesOrder->warehouse->company_name : $salesOrder->warehouse->name;

        $message = "Terima kasih atas pesanannya di " . ($warehouseName ?? '') . ". Detail pesanan:";
        $message .= PHP_EOL;
        $message .= PHP_EOL;

        $order = 1;
        foreach ($salesOrder->details as $salesOrderDetail) {
            $message .= $order++ . ". " . $salesOrderDetail->productUnit->name . " x " . $salesOrderDetail->qty . " = *Rp " . number_format((float) $salesOrderDetail->total_price, 0, ',', '.') . "*";
            $message .= PHP_EOL;
        }

        if ($salesOrder->auto_discount_nominal > 0) {
            $message .= PHP_EOL;
            $message .= "Auto Discount            : *Rp " . number_format((float) $salesOrder->auto_discount_nominal, 0, ',', '.') . "*";
        }

        if ($salesOrder->voucher_id) {
            $message .= PHP_EOL;
            $message .= "Voucher                        : *Rp " . number_format((float) $salesOrder->raw_source['voucher_value'] ?? 0, 0, ',', '.') . "*";
        }

        if ($salesOrder->additional_discount > 0) {
            $message .= PHP_EOL;
            $message .= "Additional Discount : *Rp " . number_format((float) $salesOrder->additional_discount, 0, ',', '.') . "*";
        }

        if ($salesOrder->shipment_fee > 0) {
            $message .= PHP_EOL;
            $message .= "Delivery Fee                : *Rp " . number_format((float) $salesOrder->shipment_fee, 0, ',', '.') . "*";
        }

        $message .= PHP_EOL;
        $message .= "Grand Total                 : *Rp " . number_format((float) $salesOrder->price, 0, ',', '.') . "*";
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= $salesOrder->description;
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= "Download invoice :";
        $message .= PHP_EOL;
        $message .= 'https://platinumadisentosa.com/invoices/' . ($idHash ?? Crypt::encryptString($salesOrder->id)) . '/print';

        $phone = $salesOrder->reseller->phone;
        if ($phone[0] == '0') $phone = substr($phone, 1);
        return sprintf("https://web.whatsapp.com/send/?phone=%s&text=%s", $phone, urlencode($message));
    }

    public static function getDefaultInvoiceNo(string $warehouseCode): string
    {
        return sprintf(config('app.format_invoice_no'), date('Y'), date('m'), date('d'), sprintf('%04s', config('app.start_invoice_no', 90)), $warehouseCode);
    }

    public static function getSoNumber(Warehouse $warehouse): string
    {
        $lastInoviceNo = SalesOrder::where('is_invoice', true)
            ->whereDate('created_at', date('Y-m-d'))
            ->where('warehouse_id', $warehouse->id)
            ->where('invoice_no', 'like', '%NUSATIC%')
            ->orderByDesc('invoice_no')
            ->first(['invoice_no']);

        if ($lastInoviceNo) {
            try {
                $lastInoviceNo = explode('/', $lastInoviceNo->invoice_no)[3];
                $lastInoviceNo = sprintf(config('app.format_invoice_no'), date('Y'), date('m'), date('d'), sprintf('%04s', (int) $lastInoviceNo + 1), $warehouse->code);
            } catch (\Exception $e) {
                $lastInoviceNo = self::getDefaultInvoiceNo($warehouse->code);
            }
        } else {
            $lastInoviceNo = self::getDefaultInvoiceNo($warehouse->code);
        }

        return $lastInoviceNo;
    }
}
