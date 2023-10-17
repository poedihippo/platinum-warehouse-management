<NMEXML EximID="120" BranchCode="780239574" ACCOUNTANTCOPYID="">
    <TRANSACTIONS OnError="CONTINUE">
        <DELIVERYORDER operation="Add" REQUESTID="1">
            <TRANSACTIONID>00001</TRANSACTIONID>
            @php
                $keyId = 0;
            @endphp
            @foreach ($deliveryOrder->details ?? [] as $detail)
                <ITEMLINE operation="Add">
                    <KeyID>{{ $keyId++ }}</KeyID>
                    <ITEMNO>{{ $detail->salesOrderDetail?->productUnit?->code }}</ITEMNO>
                    <QUANTITY>{{ $detail->salesOrderDetail?->qty ?? 0 }}</QUANTITY>
                    <ITEMUNIT>{{ $detail->salesOrderDetail?->productUnit?->uom?->name }}</ITEMUNIT>
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
                    <ITEMOVDESC>{{ $detail->salesOrderDetail?->productUnit->name }}</ITEMOVDESC>
                    <UNITPRICE>{{ $detail->salesOrderDetail?->productUnit->price }}</UNITPRICE>
                    <ITEMDISCPC />
                    @if ($detail->salesOrderDetail->tax > 0)
                        <TAXCODES>T</TAXCODES>
                    @else
                        <TAXCODES />
                    @endif
                    <GROUPSEQ />
                    <SOSEQ>1</SOSEQ>
                    <BRUTOUNITPRICE>0</BRUTOUNITPRICE>
                    <WAREHOUSEID>{{ $detail->salesOrderDetail?->warehouse?->code }}</WAREHOUSEID>
                    <QTYCONTROL>0</QTYCONTROL>
                    <DOSEQ />
                    <SOID>{{ $detail->salesOrderDetail?->salesOrder?->invoice_no }}</SOID>
                    <DOID />
                </ITEMLINE>
            @endforeach
            <INVOICENO>{{ $deliveryOrder->invoice_no }}</INVOICENO>
            <INVOICEDATE>{{ date('Y-m-d', strtotime($deliveryOrder->created_at)) }}</INVOICEDATE>
            <INVOICEAMOUNT>0</INVOICEAMOUNT>
            <PURCHASEORDERNO />
            <WAREHOUSEID>{{ $deliveryOrder->warehouse?->code }}</WAREHOUSEID>
            <DESCRIPTION>{{ !empty($deliveryOrder->description) ? $deliveryOrder->description : '#Barang yang sudah dibeli tidak dapat dikembalikan. Terimakasih' }}</DESCRIPTION>
            <SHIPDATE>{{ date('Y-m-d', strtotime($deliveryOrder->shipment_estimation_datetime)) }}</SHIPDATE>
            <DELIVERYORDER />
            <CUSTOMERID>{{ $deliveryOrder->reseller?->code }}</CUSTOMERID>
            <SHIPTO1>{{ $deliveryOrder->reseller?->name }}</SHIPTO1>
            <SHIPTO2>{{ $deliveryOrder->reseller?->tax_address }}</SHIPTO2>
            <SHIPTO3>{{ $deliveryOrder->reseller?->city ?? '' }}</SHIPTO3>
            <SHIPTO4>{{ $deliveryOrder->reseller?->province ?? '' }}</SHIPTO4>
            <SHIPTO5>{{ $deliveryOrder->reseller?->country ?? '' }}</SHIPTO5>
            <CURRENCYNAME>IDR</CURRENCYNAME>
            <AUTOMATICINSERTGROUPING />
        </DELIVERYORDER>
    </TRANSACTIONS>
</NMEXML>
