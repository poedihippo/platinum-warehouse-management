<!DOCTYPE html>
<html>
<head>
    <title>Sales Order {{ $salesOrder->code }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
    <p>SO : {{ $salesOrder->code }}</p>
    <p>Date : {{ date('d-m-Y H:i:s', strtotime($salesOrder->transaction_date)) }}</p>
    <p>Customer : {{ $salesOrder->reseller?->name ?? '' }}</p>
    <p>Phone Number : {{ $salesOrder->reseller?->phone ?? '' }}</p>
    <p>Items : {{ $salesOrder->details->count() }}</p>
    <p>Amount : {{ $salesOrder->price ?? 0 }}</p>
    <p>Est. Shipment : {{ date('d-m-Y H:i:s', strtotime($salesOrder->shipment_estimation_datetime)) }}</p>
    <table border="1">
        <thead>
            <tr>
                <th>Item No</th>
                <th>Description</th>
                <th>Category</th>
                <th>Brand</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Price</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($salesOrder->details ?? [] as $detail)
                <tr>
                    <td>{{ $detail->productUnit?->code ?? '' }}</td>
                    <td>{{ $detail->productUnit?->name ?? '' }}</td>
                    <td>{{ $detail->productUnit?->product?->productCategory?->name ?? '' }}</td>
                    <td>{{ $detail->productUnit?->product?->productBrand?->name ?? '' }}</td>
                    <td>{{ $detail->qty ?? 0 }}</td>
                    <td>{{ $detail->productUnit?->uom?->name ?? '' }}</td>
                    <td>{{ $detail->productUnit?->price ?? 0 }}</td>
                    <td>{{ $detail->productUnit?->price * $detail->qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
