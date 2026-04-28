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
            font-size: 15px;
            margin: 0 !important;
            padding: 0 !important;
        }

        .container {
            margin-top: 80px;
        }

        .delivery-info {
            width: 100%;
        }

        .table-container {
            /* margin-left: -15px; */
            margin-top: 30px;
            width: 100%;
            height: 210px;
        }

        .note {
            margin-top: 30px;
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
    @php
        $totalQty = 0;
    @endphp
    @if ($deliveryOrderDetailsChunk->count() > 0)
        @php
            $totalChunk = $deliveryOrderDetailsChunk->count();
            $countLoop = 1;
        @endphp
        @foreach ($deliveryOrderDetailsChunk as $deliveryOrderDetails)
            @php
                $countLoop++;
            @endphp
            <div class="container">
                <table class="delivery-info">
                    <tr>
                        <td style="width: 70%; vertical-align: top;">{{ $deliveryOrder->reseller?->name ?? '' }}</td>
                        <td>
                            <table>
                                <tr>
                                    <td>{{ $deliveryOrder->invoice_no }}</td>
                                </tr>
                                <tr>
                                    <td><p style="margin-top: 20px">{{ date('d M Y', strtotime($deliveryOrder->transaction_date)) }}</p></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table class="table-container">
                    @forelse ($deliveryOrderDetails as $detail)
                        @php
                            $totalQty += $detail->salesOrderDetail?->qty ?? 0;
                        @endphp
                        <tr>
                            <td style="width: 75%;">{{ $detail->salesOrderDetail?->productUnit?->code ?? '-' }} | {{ $detail->salesOrderDetail?->productUnit?->name ?? '-' }}</td>
                            <td class="text-center" style="width: 25%; padding-left: 10px;">
                            {{ $detail->salesOrderDetail?->qty ?? 0 }} &nbsp; {{ $detail->salesOrderDetail?->productUnit?->uom?->name ?? '' }}</td>
                        </tr>
                    @empty
                    @endforelse
                </table>
                <table style="width: 100%; margin-top: 30px;">
                    <tr>
                        <td style="width: 75%;">{{ $deliveryOrder->description }}</td>
                        <td class="text-center" style="width: 25%; padding-left: 10px;">{{ $totalQty }}</td>
                    </tr>
                </table>
            </div>
            @if ($totalChunk >= $countLoop)
                <div class="page-break"></div>
            @endif
        @endforeach
    @else
        <div class="container">
            <table class="delivery-info">
                <tr>
                    <td style="width: 70%; vertical-align: top;">{{ $deliveryOrder->reseller?->name ?? '' }}</td>
                    <td>
                        <table>
                            <tr>
                                <td>{{ $deliveryOrder->invoice_no }}</td>
                            </tr>
                            <tr>
                                <td><p style="margin-top: 20px">{{ date('d M Y', strtotime($deliveryOrder->transaction_date)) }}</p></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table class="table-container">
                @forelse ($deliveryOrder->details as $detail)
                    @php
                        $totalQty += $detail->salesOrderDetail?->qty ?? 0;
                    @endphp
                      <tr>
                            <td style="width: 75%;">{{ $detail->salesOrderDetail?->productUnit?->code ?? '-' }} | {{ $detail->salesOrderDetail?->productUnit?->name ?? '-' }}</td>
                            <td class="text-center" style="width: 25%; padding-left: 10px;">
                            {{ $detail->salesOrderDetail?->qty ?? 0 }} &nbsp; {{ $detail->salesOrderDetail?->productUnit?->uom?->name ?? '' }}</td>
                        </tr>
                @empty
                @endforelse
            </table>
             <table style="width: 100%; margin-top: 30px;">
                <tr>
                    <td style="width: 75%;">{{ $deliveryOrder->description }}</td>
                    <td class="text-center" style="width: 25%; padding-left: 10px;">{{ $totalQty }}</td>
                </tr>
            </table>
        </div>
    @endif
</body>

</html>
