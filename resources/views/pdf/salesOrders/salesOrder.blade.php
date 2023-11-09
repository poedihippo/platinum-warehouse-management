<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Document</title>
    <style>
        body {
            font-size: 12px;
            margin: 0;
            padding: 0;
        }

        .page-break {
            page-break-after: always !important;
        }

        .location {
            border-width: 1px;
            border-color: black;
            border-style: solid;
            border-radius: 8px;
        }

        .locationTop {
            border-width: 1px;
            border-color: black;
            border-style: solid;
            border-radius: 8px;
        }

        .dotted {
            border-width: 1px;
            border-color: black;
            border-style: dashed;
        }

        .table-border-radius {
            width: 100%;
            border: 1px solid;
            border-radius: 8px;
            border-spacing: 0;
        }

        .border-right-dashed {
            border-right: 1px solid;
            border-right-style: dashed;
        }

        .border-right-bottom-dashed {
            border-bottom: 1px solid;
            border-right: 1px solid;
            border-bottom-style: dashed;
            border-right-style: dashed;
        }

        .border-left-bottom-dashed {
            border-bottom: 1px solid;
            border-bottom-style: dashed;
        }

        .border {
            border: 1px solid;
        }

        .border-top {
            border-top: 1px solid;
        }

        .border-bottom {
            border-bottom: 1px solid;
        }

        .border-right {
            border-right: 1px solid;
        }

        .border-left {
            border-left: 1px solid;
        }

        .border-radius-8 {
            border-radius: 8px;
        }

        .border-radius-3 {
            border-radius: 3px;
        }

        .m-0 {
            margin: 0;
        }

        .mx-5 {
            margin-right: 5px;
            margin-left: 5px;
        }

        .my-5 {
            margin-top: 5px;
            margin-bottom: 5px;
        }

        .mt-5 {
            margin-top: 5px;
        }

        .mr-5 {
            margin-right: 5px;
        }

        .ml-20 {
            margin-left: 20px;
        }

        .py-5 {
            padding-top: 5px;
            padding-bottom: 5px;
        }

        .py-3 {
            padding-top: 3px;
            padding-bottom: 3px;
        }

        .pt-5 {
            padding-top: 5px;
        }

        .pb-5 {
            padding-bottom: 5px;
        }

        .width-50 {
            width: 50%;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .text-bold {
            font-weight: bold;
        }

        .table-items-bordered {
            width: 100%;
            border: 1px solid;
        }

        .border-bottom-none {
            border-bottom: none !important;
        }
    </style>
</head>
<body>
    @foreach ($salesOrderDetails as $key => $orderDetails)
        @if ($key != 0)
            <div class="page-break"></div>
        @endif
        @php
            $listProductsBlackSpace = max(10 - $orderDetails->count() ?? 0, 0);
        @endphp
        <div>
            <table style="width: 100%">
                <tr>
                    <td style="width: 60%">
                        <table style="width: 100%">
                            <tr>
                                <td></td>
                                <td>
                                    <div class="locationTop py-5">
                                        <h3 class="m-0 mx-5">PT. PLATINUM ADI SENTOSA</h3>
                                        <div class="dotted"></div>
                                        <p class="m-0 mx-5">
                                            KO Duta Indah Iconic Blok B no. 17 <br />RT.004 RW02 Kel.
                                            Panunggangan Utara <br />
                                            Pinang Kota Tangerang Banteng
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Order by</td>
                                <td>
                                    <div class="locationTop py-5">
                                        <h3 class="m-0 mx-5">{{ $salesOrder->reseller?->name }}</h3>
                                        <p class="m-0 mx-5">
                                            {{ $salesOrder->reseller?->address }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Ship To</td>
                                <td>
                                    <div class="locationTop py-5">
                                        <h3 class="m-0 mx-5">{{ $salesOrder->reseller->name }}</h3>
                                        <p class="m-0 mx-5">
                                            {{ $salesOrder->reseller?->address }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 40%">
                        <div class="ml-20">
                            <h1 class="m-0 text-right">Sales Order</h1>
                            <table class="table-border-radius">
                                <tr>
                                    <td class="border-right-bottom-dashed py-3">
                                        <p class="mx-5 m-0">SO Date</p>
                                        <p class="mx-5 m-0 ml-20">
                                            {{ date('d M Y', strtotime($salesOrder->transaction_date)) }}</p>
                                    </td>
                                    <td class="border-left-bottom-dashed py-3">
                                        <p class="mx-5 m-0">SO Number</p>
                                        <p class="mx-5 m-0 ml-20">{{ $salesOrder->invoice_no }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border-right-bottom-dashed py-3">
                                        <p class="mx-5 m-0">Terms</p>
                                        <p class="mx-5 m-0 ml-20">C.O.D</p>
                                    </td>
                                    <td class="border-left-bottom-dashed py-3">
                                        <p class="mx-5 m-0">FOB</p>
                                        <p class="mx-5 m-0 ml-20">&nbsp;</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border-right-bottom-dashed py-3">
                                        <p class="mx-5 m-0">Ship Via</p>
                                        <p class="mx-5 m-0 ml-20">&nbsp;</p>
                                    </td>
                                    <td class="border-left-bottom-dashed py-3">
                                        <p class="mx-5 m-0">Ship Date</p>
                                        <p class="mx-5 m-0 ml-20">
                                            {{ date('d M Y', strtotime($salesOrder->shipment_estimation_datetime)) }}
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border-right-dashed py-3">
                                        <p class="mx-5 m-0">PO. No.</p>
                                        <p class="mx-5 m-0 ml-20">&nbsp;</p>
                                    </td>
                                    <td class="py-3">
                                        <p class="mx-5 m-0">Salesman</p>
                                        <p class="mx-5 m-0 ml-20">&nbsp;</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="mt-5">
            <table class="table-items-bordered @if($key != $lastOrderDetailsKey)border-bottom-none @endif" cellspacing="0">
                <tr>
                    <th class="border-bottom border-right">Item</th>
                    <th class="border-bottom border-right">Item Description</th>
                    <th class="border-bottom border-right">Qty</th>
                    <th class="border-bottom border-right">Unit Price</th>
                    <th class="border-bottom border-right">Disc</th>
                    <th class="border-bottom border-right">Tax</th>
                    <th class="border-bottom">Amount</th>
                </tr>
                @foreach ($orderDetails as $detail)
                    <tr>
                        <td class="border-right">
                            <span
                                class="mx-5">{{ $detail->productUnit?->code . (is_null($detail->packaging_id) ? '' : '*') }}</span>
                        </td>
                        <td class="border-right">
                            <span
                                class="mx-5">{{ $detail->productUnit?->name . (is_null($detail->packaging_id) ? '' : '*') }}</span>
                        </td>
                        <td class="border-right text-right">
                            <span class="mx-5">{{ $detail->qty }}</span>
                        </td>
                        <td class="border-right text-right">
                            <span class="mx-5">{{ number_format($detail->unit_price) }}</span>
                        </td>
                        <td class="border-right">
                            <span class="mx-5">{{ $detail->discount }}</span>
                        </td>
                        <td class="border-right">
                            <span class="mx-5">{{ $detail->tax }}</span>
                        </td>
                        <td class="text-right">
                            <span class="mx-5">{{ number_format($detail->total_price) }}</span>
                        </td>
                    </tr>
                @endforeach
                <tr>
                    @for ($i = 0; $i < 7; $i++)
                        <td class="border-right">
                            {!! str_repeat('<br>', $listProductsBlackSpace) !!}
                        </td>
                    @endfor
                </tr>
            </table>
        </div>
        @if ($key == $lastOrderDetailsKey)
            <div class="mt-5">
                <table style="width: 100%">
                    <tr>
                        <td style="width: 65%">
                            <table style="width: 100%">
                                <tr>
                                    <td>Say :</td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="border border-radius-8">
                                            <p class="mx-5 my-5">{{ $spellTotalPrice ?? ($salesOrder->price ?? 0) }}
                                            </p>
                                            <br>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <table style="width: 100%">
                                <tr>
                                    <td>Description :</td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="border border-radius-8">
                                            <p class="mx-5 my-5">
                                                {{ $salesOrder->description }}
                                                <br>
                                                {!! $bankTransferInfo !!}
                                            </p>
                                            <br>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <table style="width: 50%">
                                <tr>
                                    <td>
                                        <p class="text-center">Prepared by</p>
                                        <br />
                                        <br />
                                        <br />
                                        <br />
                                        <br />
                                        <div>
                                            <hr />
                                        </div>
                                        <span>Date:</span>
                                    </td>
                                    <td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                                    <td>
                                        <p class="text-center">Approved by</p>
                                        <br />
                                        <br />
                                        <br />
                                        <br />
                                        <br />
                                        <div>
                                            <hr />
                                        </div>
                                        <span>Date:</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 35%; vertical-align: top;">
                            <table class="table-border-radius">
                                <tr>
                                    <td class="width-50 border-bottom text-right py-3">Sub Total :</td>
                                    <td class="width-50 border-bottom text-right py-3">
                                        <span class="mr-5">{{ number_format($salesOrder->details->sum('total_price')) }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="width-50 text-right py-3">Discount :</td>
                                    <td class="width-50 text-right py-3">
                                        <span class="mr-5">{{ number_format($salesOrder->additional_discount) }}</span>
                                    </td>
                                </tr>
                            </table>
                            <table class="mt-5 table-border-radius">
                                <tr>
                                    <td class="width-50 border-bottom text-right py-3">PPN :</td>
                                    <td class="width-50 border-bottom text-right py-3">
                                        <span class="mr-5">0</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="width-50 text-right py-3">:</td>
                                    <td class="width-50 text-right py-3">
                                        <span class="mr-5">0</span>
                                    </td>
                                </tr>
                            </table>
                            <table class="mt-5 table-border-radius">
                                <tr>
                                    <td class="width-50 text-right py-3">Extimated Freight :</td>
                                    <td class="width-50 text-right py-3">
                                        <span class="mr-5">{{ number_format($salesOrder->shipment_fee) }}</span>
                                    </td>
                                </tr>
                            </table>
                            <table class="mt-5 table-border-radius">
                                <tr>
                                    <td class="width-50 text-right text-bold py-3">Total Order :</td>
                                    <td class="width-50 text-right text-bold py-3">
                                        <span class="mr-5">{{ number_format($salesOrder->price) }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </div>
        @endif
    @endforeach
</body>
</html>
