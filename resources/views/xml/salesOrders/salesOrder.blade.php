<NMEXML EximID="120" BranchCode="780239574" ACCOUNTANTCOPYID="">
    <TRANSACTIONS OnError="CONTINUE">
        <SALESORDER operation="Add" REQUESTID="1">
            <TRANSACTIONID>00001</TRANSACTIONID>
            @php
                $keyId = 0;
                $GROUPSEQ = 0;
            @endphp
            @foreach ($salesOrder->details ?? [] as $detail)
                {{-- @if ($detail->packaging) --}}
                @if (false)
                    @for ($i = 0; $i < 2; $i++)
                        <ITEMLINE operation="Add">
                            <KeyID>{{ $keyId++ }}</KeyID>
                            <ITEMNO>{{ $detail->productUnit?->code . ($i == 0 ? '*' : '') }}</ITEMNO>
                            <QUANTITY>{{ $detail->qty }}</QUANTITY>
                            <ITEMUNIT>{{ $detail->productUnit?->uom?->name }}</ITEMUNIT>
                            <UNITRATIO>1</UNITRATIO>
                            <ITEMRESERVED1 />
                            <ITEMRESERVED2 />
                            <ITEMRESERVED3 />
                            <ITEMRESERVED4 />
                            <ITEMRESERVED5 />
                            <ITEMRESERVED6 />
                            <ITEMRESERVED7 />
                            <ITEMRESERVED8 />
                            <ITEMRESERVED9 />
                            <ITEMRESERVED10 />
                            <ITEMOVDESC>{{ ($i == 0 ? '' : '--') . $detail->productUnit->name . ($i == 0 ? '*' : '') }}</ITEMOVDESC>
                            <UNITPRICE>{{ $i == 0 ? $detail->unit_price : 0 }}</UNITPRICE>
                            <DISCPC />
                            @if ($detail->tax > 0)
                                <TAXCODES>T</TAXCODES>
                            @else
                                <TAXCODES />
                            @endif
                            <GROUPSEQ>{{ ($i == 0 ? null : $GROUPSEQ) }}</GROUPSEQ>
                            <QTYSHIPPED>0</QTYSHIPPED>
                        </ITEMLINE>
                    @endfor
                    <ITEMLINE operation="Add">
                        <KeyID>{{ $keyId++ }}</KeyID>
                        <ITEMNO>{{ $detail->packaging->code }}</ITEMNO>
                        <QUANTITY>{{ $detail->qty }}</QUANTITY>
                        <ITEMUNIT>{{ $detail->packaging->uom?->name }}</ITEMUNIT>
                        <UNITRATIO>1</UNITRATIO>
                        <ITEMRESERVED1 />
                        <ITEMRESERVED2 />
                        <ITEMRESERVED3 />
                        <ITEMRESERVED4 />
                        <ITEMRESERVED5 />
                        <ITEMRESERVED6 />
                        <ITEMRESERVED7 />
                        <ITEMRESERVED8 />
                        <ITEMRESERVED9 />
                        <ITEMRESERVED10 />
                        <ITEMOVDESC>--{{ $detail->packaging->name }}</ITEMOVDESC>
                        <UNITPRICE>{{ $detail->packaging->price }}</UNITPRICE>
                        <DISCPC />
                        @if ($detail->tax > 0)
                            <TAXCODES>T</TAXCODES>
                        @else
                            <TAXCODES />
                        @endif
                        <GROUPSEQ>{{ $GROUPSEQ }}</GROUPSEQ>
                        <QTYSHIPPED>0</QTYSHIPPED>
                    </ITEMLINE>
                @else
                    <ITEMLINE operation="Add">
                        <KeyID>{{ $keyId++ }}</KeyID>
                        <ITEMNO>{{ $detail->productUnit?->code }}</ITEMNO>
                        <QUANTITY>{{ $detail->qty }}</QUANTITY>
                        <ITEMUNIT>{{ $detail->productUnit?->uom?->name }}</ITEMUNIT>
                        <UNITRATIO>1</UNITRATIO>
                        <ITEMRESERVED1 />
                        <ITEMRESERVED2 />
                        <ITEMRESERVED3 />
                        <ITEMRESERVED4 />
                        <ITEMRESERVED5 />
                        <ITEMRESERVED6 />
                        <ITEMRESERVED7 />
                        <ITEMRESERVED8 />
                        <ITEMRESERVED9 />
                        <ITEMRESERVED10 />
                        <ITEMOVDESC>{{ $detail->productUnit->name }}</ITEMOVDESC>
                        <UNITPRICE>{{ $detail->unit_price }}</UNITPRICE>
                        <DISCPC />
                        @if ($detail->tax > 0)
                            <TAXCODES>T</TAXCODES>
                        @else
                            <TAXCODES />
                        @endif
                        <GROUPSEQ />
                        <QTYSHIPPED>0</QTYSHIPPED>
                    </ITEMLINE>
                @endif
                @php
                    $GROUPSEQ++
                @endphp
            @endforeach
            <SONO>{{ $salesOrder->invoice_no }}</SONO>
            <SODATE>{{ date('Y-m-d', strtotime($salesOrder->transaction_date)) }}</SODATE>
            <TAX1ID>T</TAX1ID>
            <TAX1CODE>T</TAX1CODE>
            <TAX2CODE />
            <TAX1RATE>11</TAX1RATE>
            <TAX2RATE>0</TAX2RATE>
            <TAX1AMOUNT>{{ $salesOrder->details->sum('tax') ?? 0 }}</TAX1AMOUNT>
            <TAX2AMOUNT>0</TAX2AMOUNT>
            <RATE>1</RATE>
            <TAXINCLUSIVE>0</TAXINCLUSIVE>
            <CUSTOMERISTAXABLE>1</CUSTOMERISTAXABLE>
            <CASHDISCOUNT>0</CASHDISCOUNT>
            <CASHDISCPC />
            <FREIGHT>{{ $salesOrder->shipment_fee ?? 0 }}</FREIGHT>
            <TERMSID>C.O.D</TERMSID>
            <FOB />
            <ESTSHIPDATE>{{ date('Y-m-d', strtotime($salesOrder->shipment_estimation_datetime)) }}</ESTSHIPDATE>
            <DESCRIPTION>
                {{ !empty($salesOrder->description) ? $salesOrder->description : '#Barang yang sudah dibeli tidak dapat dikembalikan. Terimakasih' }}
            </DESCRIPTION>
            <SHIPTO1>{{ $salesOrder->reseller?->name }}</SHIPTO1>
            <SHIPTO2>{{ $salesOrder->reseller?->tax_address }}</SHIPTO2>
            <SHIPTO3>{{ $salesOrder->reseller?->city ?? '' }}</SHIPTO3>
            <SHIPTO4>{{ $salesOrder->reseller?->province ?? '' }}</SHIPTO4>
            <SHIPTO5>{{ $salesOrder->reseller?->country ?? '' }}</SHIPTO5>
            <DP>0</DP>
            <DPACCOUNTID>2102.001</DPACCOUNTID>
            <DPUSED />
            <CUSTOMERID>{{ $salesOrder->reseller?->code }}</CUSTOMERID>
            <PONO />
            <CURRENCYNAME>IDR</CURRENCYNAME>
        </SALESORDER>
    </TRANSACTIONS>
</NMEXML>
