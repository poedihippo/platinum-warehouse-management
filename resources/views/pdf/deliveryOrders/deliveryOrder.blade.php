<!DOCTYPE html>
<html>

<head>
    <title>Sales Order {{ $deliveryOrder->salesOrder?->code }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>

<body>
    <p>DO : {{ $deliveryOrder->code }}</p>
    <p>Date : {{ date('d-m-Y H:i:s', strtotime($deliveryOrder->salesOrder?->transaction_date)) }}</p>
    <p>Customer : {{ $deliveryOrder->salesOrder?->reseller?->name ?? '' }}</p>
    <p>Phone Number : {{ $deliveryOrder->salesOrder?->reseller?->phone ?? '' }}</p>
    <p>Items : {{ $deliveryOrder->salesOrder?->details->count() }}</p>
    <p>Amount : {{ $deliveryOrder->salesOrder?->price ?? 0 }}</p>
    <p>Est. Shipment : {{ date('d-m-Y H:i:s', strtotime($deliveryOrder->salesOrder?->shipment_estimation_datetime)) }}</p>
    <table border="1">
        <thead>
            <tr>
                <th>Item No</th>
                <th>Description</th>
                <th>Category</th>
                <th>Brand</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Verified</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($deliveryOrder->salesOrder?->details ?? [] as $detail)
                <tr>
                    <td>{{ $detail->productUnit?->code ?? '' }}</td>
                    <td>{{ $detail->productUnit?->name ?? '' }}</td>
                    <td>{{ $detail->productUnit?->product?->productCategory?->name ?? '' }}</td>
                    <td>{{ $detail->productUnit?->product?->productBrand?->name ?? '' }}</td>
                    <td>{{ $detail->qty ?? 0 }}</td>
                    <td>{{ $detail->productUnit?->uom?->name ?? '' }}</td>
                    <td>{{ $detail->sales_order_items_count ?? 0 }}/({{ $detail->qty }})</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
