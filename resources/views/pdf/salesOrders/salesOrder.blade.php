<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Order {{ $salesOrder->invoice_no }}</title>
    <style>
        #delivery-info {
            width: 100%;
            margin-bottom: 10px;
        }

        .table-container {
            width: 100%;
            border: 1px solid black;
            /* Add this line to add border */
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-container th,
        .table-container td {
            text-align: center;
            padding: 8px;
            border-bottom: 1px solid black;
        }

        .table-container thead {
            font-weight: bold;
        }

        .table-container tfoot td:first-child {
            text-align: right;
            font-weight: bold;
        }

        .demo {
            width: 100%;
            border: 1px solid #000000;
            border-collapse: collapse;
            padding: 5px;
        }

        .demo th,
        .demo td {
            border: 1px solid #000000;
            padding: 5px;
        }

        .empty-cell {
            border: none;
            /* Remove the border */
        }

        .demo1 {
            width: 100%;
            border: 1px solid #000000;
            border-collapse: collapse;
            padding: 5px;
        }

        .demo1 th {
            border: 1px solid #000000;
            padding: 5px;
        }

        .demo1 td {
            border: 1px solid #000000;
            padding: 5px;
        }

        .margin-0{
            margin: 0 !important;
        }
    </style>

</head>

<body>
    <div>
        <!-- Logo dan alamat -->
        <table>
            <tr>
                <td>
                    <img src="{{ public_path('images/logo-platinum.png') }}" alt="plat_logo"
                        style="background-color: blue; width: 115px; height: 115px; margin-right: 10px; background: black;" />
                </td>
                <td>
                    <h1 style="font-weight: bold;" class="margin-0">PT. PLATINUM ADI SENTOSA</h1>
                    <span>Ko Duta Indah Iconic Blok B no. 17</span>
                    <br>
                    <span>RT. 004 RW. 02 Kel. Panunggangan Utara Pinang</span>
                    <br>
                    <span>Kota Tangerang Banten</span>
                    <br>
                    <span>Telp: (62-21) 2986-6646 / 2986-6656</span>
                    <br>
                    <span>NPWP: 75.897.768.0-416.000</span>
                </td>
            </tr>
        </table>

        <!-- Sales Order -->
        <h1 class="margin-0" style="text-align: right;">SALES ORDER</h1>

        <!-- Delivery To -->
            <table id="delivery-info">
                <tr>
                    <td>
                        <h3 class="margin-0">DELIVERY TO: &nbsp; {{$salesOrder->reseller?->name ?? ''}}</h3>
                    </td>
                    <td>
                        <table>
                            <tr>
                                <td><h3 class="margin-0">SO no</h3></td>
                                <td>: {{$salesOrder->invoice_no}}</td>
                            </tr>
                            <tr>
                                <td><h3 class="margin-0">Date</h3></td>
                                <td>: {{date('d M Y', strtotime($salesOrder->transaction_date))}}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ITEM NO</th>
                        <th>DESCRIPTION</th>
                        <th>QTY</th>
                        <th>AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salesOrder->details as $detail)
                    <tr>
                        <td>{{ $detail->productUnit?->code ?? '-' }}</td>
                        <td>{{ $detail->productUnit?->name ?? '-' }}</td>
                        <td>{{ $detail->qty ?? 0 }}</td>
                        <td>{{ $detail->productUnit?->uom?->name ?? '--' }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">TOTAL</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Signature -->
        <div style="margin-top: 1rem;">
            <table class="demo">
                <tbody>
                    <tr>
                        <td style="border-bottom: 0;">&nbsp;Good Received By:<br><br></td>
                        <td>&nbsp;Security By:<br><br></td>
                        <td>&nbsp;Prepared By:<br><br></td>
                    </tr>
                    <tr>
                        <td style="border-top: 0;">&nbsp;</td>
                        <td>&nbsp;Good Delivered By:<br><br></td>
                        <td>&nbsp;Sales By:<br></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            <table class="demo">
                <tbody>
                    <tr>
                        <td>&nbsp;Notes:<br><br><br></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
