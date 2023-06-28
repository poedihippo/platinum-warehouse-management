<!DOCTYPE html>
<html>

<head>
    <title>Sales Order {{ $deliveryOrder->salesOrder?->code }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" href="{{ public_path('css/bootstrap.min.css') }}">
</head>

<body>
    <div class="row">
        <div class="col">
            <h2>PT. PLATINUM ADI SENTOSA</h2>
            <p class="my-0 fw-medium">Ko Duta Indah Iconic Blok B no. 17</p>
            <p class="my-0 fw-medium">RT. 004 RW. 02 Kel. Panunggangan Utara Pinang</p>
            <p class="my-0 fw-medium">Kota Tangerang Banten</p>
            <p class="my-0 fw-medium">Telp. (62-21) 2986-6646 / 2986-6656</p>
            <p class="my-0 fw-medium">NPWP : 75.897.768.0-416.000</p>
        </div>
        <div class="col">
            <h2 class="text-end">DELIVERY ORDER</h2>
        </div>
    </div>
    <div class="row">
        <div class="col-6">
            <table>
                <tr>
                    <th>DELIVERY TO</th>
                    <th>: Hinode Koi</th>
                </tr>
            </table>
        </div>
        <div class="col-6">
            <table>
                <tr>
                    <th>DO no</th>
                    <th>: PAS/DO/06/23/07</th>
                </tr>
                <tr>
                    <th>Date</th>
                    <th>: 5 Jun 2023</th>
                </tr>
            </table>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <table border="1">
                <thead>
                    <tr>
                        <th>ITEM NO</th>
                        <th>DESCRIPTION</th>
                        <th>QTY</th>
                        <th>AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td></td>
                        <td>JPD Shori M @ 15 Kg</td>
                        <td></td>
                        <td>1 bag</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>JPD Shori M @ 15 Kg</td>
                        <td></td>
                        <td>1 bag</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>JPD Shori M @ 15 Kg</td>
                        <td></td>
                        <td>1 bag</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end">TOTAL</td>
                        <td>1 bag</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-12">
            <table border="1">
                <tr>
                <tr>
                    <th style="border: 1px solid" rowspan="2">GOOD RECEIVED BY :</th>
                    <th style="border: 1px solid">SECURITY BY :</th>
                    <th style="border: 1px solid">PREPARED BY :</th>
                </tr>
                <tr>
                    <th style="border: 1px solid">GOOD DELIVERED BY :</th>
                    <th style="border: 1px solid">SALES BY :</th>
                </tr>
            </table>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-12">
            <table border="1">
                <tr>
                <tr>
                    <th style="border: 1px solid" rowspan="2">GOOD RECEIVED BY :</th>
                    <th style="border: 1px solid">SECURITY BY :</th>
                    <th style="border: 1px solid">PREPARED BY :</th>
                </tr>
                <tr>
                    <th style="border: 1px solid">GOOD DELIVERED BY :</th>
                    <th style="border: 1px solid">SALES BY :</th>
                </tr>
            </table>
        </div>
    </div>
    <p class="fw-bold">DO : {{ $deliveryOrder->code }}</p>
    <p>Date : {{ date('d-m-Y H:i:s', strtotime($deliveryOrder->salesOrder?->transaction_date)) }}</p>
    <p>Customer : {{ $deliveryOrder->salesOrder?->reseller?->name ?? '' }}</p>
    <p>Phone Number : {{ $deliveryOrder->salesOrder?->reseller?->phone ?? '' }}</p>
    <p>Items : {{ $deliveryOrder->salesOrder?->details->count() }}</p>
    <p>Amount : {{ $deliveryOrder->salesOrder?->price ?? 0 }}</p>
    <p>Est. Shipment :
        {{ date('d-m-Y H:i:s', strtotime($deliveryOrder->salesOrder?->shipment_estimation_datetime)) }}
    </p>
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
