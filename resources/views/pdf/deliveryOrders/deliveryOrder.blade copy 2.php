<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Order {{ $deliveryOrder->invoice_no }}</title>
    <style>
        body {
            position: relative;
            font-weight: bold;
            font-size: 18px;
            margin: 0 !important;
            padding: 0 !important;
        }

        .container {
            margin-top: 130px;
        }

        .delivery-info {
            margin-left: 95px;
            width: 100%;
        }

        .table-container {
            margin-left: -15px;
            margin-top: 40px;
            width: 100%;
        }

        .note {
            position: absolute;
            bottom: -47;
        }

        .text-center {
            text-align: center !important;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <div class="container">
        <table class="delivery-info">
            <tr>
                <td style="width: 47%; vertical-align: top;">{{ $deliveryOrder->reseller?->name ?? '' }}</td>
                <td>
                    <table>
                        <tr>
                            <td style="padding-bottom: 3px">{{ $deliveryOrder->invoice_no }}</td>
                        </tr>
                        <tr>
                            <td>{{ date('d M Y', strtotime($deliveryOrder->transaction_date)) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <table class="table-container">
            <tbody>
                @forelse ($deliveryOrder->details as $detail)
                <tr>
                    <td style="width: 90.7px">{{ $detail->salesOrderDetail?->productUnit?->code ?? '-' }}</td>
                    <td style="width: 385.5px; padding-left: 10px;">
                        {{ $detail->salesOrderDetail?->productUnit?->name ?? '-' }}
                    </td>
                    <td class="text-center" style="width: 68px; padding-left: 10px;">
                        {{ $detail->salesOrderDetail?->qty ?? 0 }}
                    </td>
                    <td class="text-center" style="width: 151.18px;">
                        {{ $detail->salesOrderDetail?->productUnit?->uom?->name ?? '' }}
                    </td>
                </tr>
                @empty
                @endforelse
            </tbody>
        </table>
        <p class="note">{{ $deliveryOrder->description }}</p>
    </div>
</body>

</html>
