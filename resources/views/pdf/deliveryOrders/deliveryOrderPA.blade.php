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
            margin-top: 35px;
            width: 100%;
            height: 210px;
        }

        .note {
            margin-top: 30px;
        }

        .text-center {
            text-align: center !important;
        }

        .ml-30 {
            margin-left: 30px;
        }

        .ml-40 {
            margin-left: 40px;
        }

        .ml--20 {
            margin-left: -20px;
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
                        <td style="width: 50%; vertical-align: top;">
                            <div class="ml--20">
                                <p style="margin-top: 0;">{{ $deliveryOrder->reseller?->name ?? '' }}</p>
                                <p>{{ $deliveryOrder->reseller?->address }}</p>
                            </div>
                        </td>
                        <td>
                            <table style="margin-left: 140px; margin-top: -10px;">
                                <tr>
                                    <td>
                                        <span class="ml-30">{{ $deliveryOrder->invoice_no }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><p class="ml-30" style="margin-top: 30px">{{ date('d F Y', strtotime($deliveryOrder->transaction_date)) }}</p></td>
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
                            <td style="width: 80%;">
                                <span class="ml--20">{{ $detail->salesOrderDetail?->productUnit?->code ?? '-' }} | {{ $detail->salesOrderDetail?->productUnit?->name ?? '-' }}</span>
                            </td>
                            <td class="text-center" style="width: 20%; padding-left: 10px;">
                                <span class="ml-40">{{ $detail->salesOrderDetail?->qty ?? 0 }} &nbsp; {{ $detail->salesOrderDetail?->productUnit?->uom?->name ?? '' }}</span>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                </table>
                <table style="width: 100%; margin-top: 50px;">
                    <tr>
                        <td style="width: 75%;">
                            <span class="ml--20">{{ $deliveryOrder->description }}</span>
                        </td>
                        <td class="text-center" style="width: 25%; padding-left: 10px;">
                            <span class="ml-40">{{ $totalQty }}</span>
                        </td>
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
                    <td style="width: 50%; vertical-align: top;">
                        <div class="ml--20">
                            <p style="margin-top: 0;">{{ $deliveryOrder->reseller?->name ?? '' }}</p>
                            <p>{{ $deliveryOrder->reseller?->address }}</p>
                        </div>
                    </td>
                    <td>
                        <table style="margin-left: 140px; margin-top: -10px;">
                            <tr>
                                <td>
                                    <span class="ml-30">{{ $deliveryOrder->invoice_no }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td><p class="ml-30" style="margin-top: 30px">{{ date('d F Y', strtotime($deliveryOrder->transaction_date)) }}</p></td>
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
                        <td style="width: 80%;">
                            <span class="ml--20">{{ $detail->salesOrderDetail?->productUnit?->code ?? '-' }} | {{ $detail->salesOrderDetail?->productUnit?->name ?? '-' }}</span>
                        </td>
                        <td class="text-center" style="width: 20%; padding-left: 10px;">
                            <span class="ml-40">{{ $detail->salesOrderDetail?->qty ?? 0 }} &nbsp; {{ $detail->salesOrderDetail?->productUnit?->uom?->name ?? '' }}</span>
                        </td>
                    </tr>
                @empty
                @endforelse
            </table>
            <table style="width: 100%; margin-top: 50px;">
                <tr>
                    <td style="width: 75%;">
                        <span class="ml--20">{{ $deliveryOrder->description }}</span>
                    </td>
                    <td class="text-center" style="width: 25%; padding-left: 10px;">
                        <span class="ml-40">{{ $totalQty }}</span>
                    </td>
                </tr>
            </table>
        </div>
    @endif
</body>
</html>