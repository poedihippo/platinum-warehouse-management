<NMEXML EximID="120" BranchCode="780239574" ACCOUNTANTCOPYID="">
    <TRANSACTIONS OnError="CONTINUE">
        <SALESORDER operation="Add" REQUESTID="1">
            <TRANSACTIONID>00001</TRANSACTIONID>
            @php
                $keyId = 0;
            @endphp
            @foreach ($deliveryOrder->salesOrder?->details ?? [] as $detail)
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
                    <UNITPRICE>{{ $detail->productUnit->price }}</UNITPRICE>
                    <ITEMDISCPC />
                    <TAXCODES />
                    <GROUPSEQ />
                    <SOSEQ>1</SOSEQ>
                    <BRUTOUNITPRICE>0</BRUTOUNITPRICE>
                    <WAREHOUSEID>{{ $deliveryOrder->salesOrder?->warehouse?->code }}</WAREHOUSEID>
                    <QTYCONTROL>0</QTYCONTROL>
                    <DOSEQ />
                    <SOID>{{ $deliveryOrder->salesOrder?->invoice_no }}</SOID>
                    <DOID />
                </ITEMLINE>
            @endforeach
            <INVOICENO>{{ $deliveryOrder->invoice_no }}</INVOICENO>
            <INVOICEDATE>{{ date('Y-m-d', strtotime($deliveryOrder->salesOrder?->transaction_date)) }}</INVOICEDATE>
            <INVOICEAMOUNT>0</INVOICEAMOUNT>
            <PURCHASEORDERNO />
            <WAREHOUSEID>{{ $deliveryOrder->salesOrder?->warehouse?->code }}</WAREHOUSEID>
            <DESCRIPTION>{{ $deliveryOrder->description }}</DESCRIPTION>
            <SHIPDATE>{{ date('Y-m-d', strtotime($deliveryOrder->salesOrder?->shipment_estimation_datetime)) }}</SHIPDATE>
            <DELIVERYORDER />
            <CUSTOMERID>{{ $deliveryOrder->salesOrder?->reseller?->code }}</CUSTOMERID>
            <SHIPTO1>{{ $deliveryOrder->salesOrder?->reseller?->name }}</SHIPTO1>
            <SHIPTO2>{{ $deliveryOrder->salesOrder?->reseller?->tax_address }}</SHIPTO2>
            <SHIPTO3 />
            <SHIPTO4 />
            <SHIPTO5 />
            <CURRENCYNAME>IDR</CURRENCYNAME>
            <AUTOMATICINSERTGROUPING />
        </SALESORDER>
    </TRANSACTIONS>
</NMEXML>
