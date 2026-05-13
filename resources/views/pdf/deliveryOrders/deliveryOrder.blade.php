<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Order {{ $deliveryOrder->invoice_no }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            position: relative;
            font-weight: bold;
            font-size: 18px;
            margin: 0 !important;
            padding: 0 !important;
        }

        .container {
            margin-top: 135px;
        }

        .container-odd {
            margin-top: 193px;
        }

        .delivery-info {
            margin-left: 95px;
            width: 100%;
        }

        .header-left {
            width: 47%;
            vertical-align: top;
        }

        .table-container {
            margin-left: -15px;
            margin-top: 30px;
            width: 100%;
        }

        .note {
            position: absolute;
            bottom: -50;
        }

        .text-center {
            text-align: center !important;
        }

        .page-break {
            page-break-after: always;
        }

        .pb-3 {
            padding-bottom: 3px;
        }
    </style>
</head>

<body>
    @if ($deliveryOrderDetailsChunk->count() > 0)
        @php
            $totalChunk = $deliveryOrderDetailsChunk->count();
            $countLoop = 1;
        @endphp
        @foreach ($deliveryOrderDetailsChunk as $deliveryOrderDetails)
            @if ($countLoop == 1)
            <div class="container">
            @else
            <div class="container-odd">
            @endif
                <table class="delivery-info">
                    <tr>
                        <td class="header-left">{{ $deliveryOrder->reseller?->name ?? '' }}</td>
                        <td>
                            <table>
                                <tr>
                                    <td class="pb-3">{{ $deliveryOrder->invoice_no }}</td>
                                </tr>
                                <tr>
                                    <td>{{ date('d M Y', strtotime($deliveryOrder->transaction_date)) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table class="table-container">
                    @forelse ($deliveryOrderDetails as $detail)
                        <tr>
                            <td style="width: 90.7px">{{ $detail->salesOrderDetail?->productUnit?->code ?? '-' }}
                            </td>
                            <td style="width: 385.5px; padding-left: 10px;">
                                {{ $detail->salesOrderDetail?->productUnit?->name ?? '-' }}
                            </td>
                            <td class="text-center" style="width: 68px; padding-left: 10px;">
                                {{ $detail->salesOrderDetail?->qty ?? 0 }}</td>
                            <td class="text-center" style="width: 151.18px;">
                                {{ $detail->salesOrderDetail?->productUnit?->uom?->name ?? '' }}
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </table>
                <p class="note">{{ $deliveryOrder->description }}</p>
            </div>
            @php
                $countLoop++;
            @endphp
            @if ($totalChunk >= $countLoop)
                <div class="page-break"></div>
            @endif
        @endforeach
    @else
        <div class="container">
            <table class="delivery-info">
                <tr>
                    <td class="header-left">{{ $deliveryOrder->reseller?->name ?? '' }}</td>
                    <td>
                        <table>
                            <tr>
                                <td class="pb-3">{{ $deliveryOrder->invoice_no }}</td>
                            </tr>
                            <tr>
                                <td>{{ date('d F Y', strtotime($deliveryOrder->transaction_date)) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table class="table-container">
                @forelse ($deliveryOrder->details as $detail)
                    <tr>
                        <td style="width: 90.7px">{{ $detail->salesOrderDetail?->productUnit?->code ?? '-' }}</td>
                        <td style="width: 385.5px; padding-left: 10px;">
                            {{ $detail->salesOrderDetail?->productUnit?->name ?? '-' }}
                        </td>
                        <td class="text-center" style="width: 68px; padding-left: 10px;">
                            {{ $detail->salesOrderDetail?->qty ?? 0 }}</td>
                        <td class="text-center" style="width: 151.18px;">
                            {{ $detail->salesOrderDetail?->productUnit?->uom?->name ?? '' }}
                        </td>
                    </tr>
                @empty
                @endforelse
            </table>
            <p class="note">{{ $deliveryOrder->description }}</p>
        </div>
    @endif
</body>
</html>