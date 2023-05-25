<NMEXML EximID="120" BranchCode="780239574" ACCOUNTANTCOPYID="">
    <TRANSACTIONS OnError="CONTINUE">
        @php
        $keyId = 0
        @endphp
        <SALESORDER operation="Add" REQUESTID="1">
            <TRANSACTIONID>00001</TRANSACTIONID>
            @foreach ($salesOrder->details as $detail)
            <ITEMLINE operation="Add">
                <KeyID>{{$detail->id}}</KeyID>
                <ITEMNO>{{$detail->productUnit?->uom?->id}}</ITEMNO>
                <QUANTITY>{{$detail->qty}}</QUANTITY>
                <ITEMUNIT>{{$detail->productUnit?->uom?->name}}</ITEMUNIT>
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
                <ITEMOVDESC>{{$detail->productUnit->name}}</ITEMOVDESC>
                <UNITPRICE>{{$detail->productUnit->price}}</UNITPRICE>
                <DISCPC />
                <TAXCODES />
                <GROUPSEQ />
                <QTYSHIPPED>4</QTYSHIPPED>
            </ITEMLINE>
            @endforeach
            <SONO>{{$salesOrder->invoice_no}}</SONO>
            <SODATE>{{ date('Y-m-d', strtotime($salesOrder->transaction_date)) }}</SODATE>
            <TAX1ID>T</TAX1ID>
            <TAX1CODE>T</TAX1CODE>
            <TAX2CODE />
            <TAX1RATE>11</TAX1RATE>
            <TAX2RATE>0</TAX2RATE>
            <TAX1AMOUNT>0</TAX1AMOUNT>
            <TAX2AMOUNT>0</TAX2AMOUNT>
            <RATE>1</RATE>
            <TAXINCLUSIVE>0</TAXINCLUSIVE>
            <CUSTOMERISTAXABLE>1</CUSTOMERISTAXABLE>
            <CASHDISCOUNT>0</CASHDISCOUNT>
            <CASHDISCPC />
            <FREIGHT>0</FREIGHT>
            <TERMSID>C.O.D</TERMSID>
            <FOB />
            <ESTSHIPDATE>{{ date('Y-m-d', strtotime($salesOrder->shipment_estimation_datetime)) }}</ESTSHIPDATE>
            <DESCRIPTION>{{$detail->note}}</DESCRIPTION>
            <SHIPTO1>Customer Pameran</SHIPTO1>
            <SHIPTO2 />
            <SHIPTO3 />
            <SHIPTO4> </SHIPTO4>
            <SHIPTO5 />
            <DP>0</DP>
            <DPACCOUNTID>2102.001</DPACCOUNTID>
            <DPUSED />
            <CUSTOMERID>CC001</CUSTOMERID>
            <PONO />
            <CURRENCYNAME>IDR</CURRENCYNAME>
        </SALESORDER>
    </TRANSACTIONS>
</NMEXML>