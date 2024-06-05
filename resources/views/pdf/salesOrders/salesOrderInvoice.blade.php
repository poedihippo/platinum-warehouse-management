<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Example 1</title>
    <style>
        .clearfix:after {
            content: "";
            display: table;
            clear: both;
        }

        a {
            color: #5D6975;
            text-decoration: underline;
        }

        body {
            position: relative;
            width: 21cm;
            height: 29.7cm;
            margin: 0 auto;
            color: #001028;
            background: #FFFFFF;
            font-family: Arial, sans-serif;
            font-size: 12px;
            font-family: Arial;
        }

        header {
            padding: 10px 0;
            margin-bottom: 30px;
        }

        #logo {
            text-align: center;
            margin-bottom: 10px;
        }

        #logo img {
            width: 90px;
        }

        h1 {
            border-top: 1px solid #5D6975;
            border-bottom: 1px solid #5D6975;
            color: #5D6975;
            font-size: 2.4em;
            line-height: 1.4em;
            font-weight: normal;
            text-align: center;
            margin: 0 0 20px 0;
            background: url(dimension.png);
        }

        #project {
            float: left;
        }

        #project span {
            color: #5D6975;
            text-align: right;
            width: 52px;
            margin-right: 10px;
            display: inline-block;
            font-size: 0.8em;
        }

        #company {
            float: right;
            text-align: right;
        }

        #project div,
        #company div {
            white-space: nowrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            margin-bottom: 20px;
        }

        table tr:nth-child(2n-1) td {
            background: #F5F5F5;
        }

        table th,
        table td {
            text-align: center;
        }

        table th {
            padding: 5px 20px;
            color: #5D6975;
            border-bottom: 1px solid #C1CED9;
            white-space: nowrap;
            font-weight: normal;
        }

        table .service,
        table .desc {
            text-align: center;
        }

        table td {
            padding: 20px;
            text-align: right;
        }

        table td.service,
        table td.desc {
            vertical-align: top;
        }

        table td.unit,
        table td.qty,
        table td.total {
            font-size: 1.2em;
        }

        table td.grand {
            border-top: 1px solid #5D6975;
        }

        #notices .notice {
            color: #5D6975;
            font-size: 1.2em;
        }

        footer {
            color: #5D6975;
            width: 100%;
            height: 30px;
            position: absolute;
            bottom: 0;
            border-top: 1px solid #C1CED9;
            padding: 8px 0;
            text-align: center;
        }

        .align-center {
            text-align: center;
        }

        .align-left {
            text-align: left;
        }

        .align-right {
            text-align: right;
        }

        .float-left {
            float: left;
        }
    </style>
</head>

<body>
    <header class="clearfix">
        <div id="logo">
            <img src="{{ public_path('images/logo-color.png') }}" />
            <img src="{{ public_path('images/logo-winkoi.jpg') }}" />
        </div>
        <h1>{{ $salesOrder->invoice_no }}</h1>
        <div id="project">
            <div>PT. PLATINUM ADI SENTOSA</div>
            <div>Ko Duta Indah Iconic Blok B No. 1 RT. 004 RW. 02 Panunggangan Utara, Pinang, Kota Tangerang, Banten
                15143</div>
            <div>(021) 29866646</div>
            <div>{{ date('d F Y', strtotime($salesOrder->created_at)) }}</div>
        </div>
    </header>
    <main>
        <table>
            <thead>
                <tr>
                    <th class="align-center">Item</th>
                    <th class="align-center">Item Description</th>
                    <th class="align-center">Qty</th>
                    <th class="align-center">Unit Price</th>
                    <th class="align-center">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($salesOrder->details as $detail)
                    <tr>
                        <td class="align-left">{{ $detail->productUnit?->code }}</td>
                        <td class="align-left">{{ $detail->productUnit?->name }}</td>
                        <td class="align-center">{{ $detail->qty }}</td>
                        <td>
                            <span class="float-left">Rp. </span>
                            <span>{{ number_format($detail->unit_price) }}</span>
                        </td>
                        <td>
                            <span class="float-left">Rp. </span>
                            <span>{{ number_format($detail->total_price) }}</span>
                        </td>
                    </tr>
                @endforeach
                {{-- <tr>
                    <td colspan="4">Sub Total</td>
                    <td class="total">$5,200.00</td>
                </tr>
                <tr>
                    <td colspan="4">TAX 25%</td>
                    <td class="total">$1,300.00</td>
                </tr> --}}
                @if ($salesOrder->additional_discount > 0)
                    <tr>
                        <td colspan="4" class="grand">ADDITIONAL DISCOUNT</td>
                        <td class="grand">
                            <span class="float-left">Rp. </span>
                            <span>{{ number_format($salesOrder->additional_discount) }}</span>
                        </td>
                    </tr>
                @endif
                @if ($salesOrder->voucher_id)
                    <tr>
                        <td colspan="4" class="@if ($salesOrder->additional_discount <= 0) grand @endif">VOUCHER</td>
                        <td class="@if ($salesOrder->additional_discount <= 0) grand @endif">
                            <span class="float-left">Rp. </span>
                            <span>{{ number_format($salesOrder->raw_source['voucher_value'] ?? 0) }}</span>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td colspan="4" class="@if (!$salesOrder->voucher_id && $salesOrder->additional_discount <= 0) grand @endif">GRAND TOTAL</td>
                    <td class="@if (!$salesOrder->voucher_id && $salesOrder->additional_discount <= 0) grand @endif">
                        <span class="float-left">Rp. </span>
                        <span>{{ number_format($salesOrder->price) }}</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" class="">PAYMENT PAID</td>
                    <td class="">
                        <span class="float-left">Rp. </span>
                        <span>{{ number_format($salesOrder->payments_sum_amount) }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
        <div id="notices">
            <div>NOTICE:</div>
            <div class="notice">{{ $salesOrder->description }}</div>
        </div>
    </main>
    <footer>
        Invoice was created on a computer and is valid without the signature and seal.
    </footer>
</body>

</html>
