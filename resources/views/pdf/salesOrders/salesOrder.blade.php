<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Order {{ $salesOrder->invoice_no }}</title>
    <style>
        body {
            position: relative;
            font-weight: bold;
            font-size: 18px;
            margin: 0 !important;
            padding: 0 !important;
        }

        #container {
            margin-top: 130px;
        }

        #delivery-info {
            margin-left: 95px;
            width: 100%;
        }

        #table-container {
            margin-left: -15px;
            margin-top: 40px;
            width: 100%;
        }

        #note {
            position: absolute;
            bottom: -60;
        }

        .text-center {
            text-align: center !important;
        }
    </style>
</head>

<body>
    <div id="container">
        <table id="delivery-info" style="width: 100%">
            <tr>
                <td style="width: 47%; vertical-align: top;">{{ $salesOrder->reseller?->name ?? '' }}</td>
                <td>
                    <table>
                        <tr>
                            <td style="padding-bottom: 3px">{{ $salesOrder->invoice_no }}</td>
                        </tr>
                        <tr>
                            <td>{{ date('d M Y', strtotime($salesOrder->transaction_date)) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <table id="table-container">
            @forelse ($salesOrder->details as $detail)
                <tr>
                    <td style="width: 90.7px">{{ $detail->productUnit?->code ?? '-' }}</td>
                    <td style="width: 385.5px; padding-left: 10px;">{{ $detail->productUnit?->name ?? '-' }}
                    </td>
                    <td class="text-center" style="width: 68px; padding-left: 10px;">{{ $detail->qty ?? 0 }}</td>
                    <td class="text-center" style="width: 151.18px;">{{ $detail->productUnit?->uom?->name ?? '' }}
                    </td>
                </tr>
            @empty
            @endforelse
        </table>
        <p id="note">{{ $salesOrder->description }}</p>
    </div>
</body>

</html>
