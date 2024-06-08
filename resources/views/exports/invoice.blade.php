<table>
    <thead>
        <tr>
            <th>invoice</th>
            <th>Booth</th>
            <th>Kasir</th>
            <th>Spg</th>
            <th>Customer</th>
            <th>Created At</th>
            <th>Type</th>
            <th>Price</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoices as $invoice)
            <tr>
                <td>{{ $invoice->invoice_no }}</td>
                <td>{{ $invoice->warehouse?->name ?? '' }}</td>
                <td>{{ $invoice->user?->name ?? '' }}</td>
                <td>{{ $invoice->spg?->name ?? '' }}</td>
                <td>{{ $invoice->reseller?->name ?? '' }}</td>
                <td>{{ date('d-m-Y H:i:s', strtotime($invoice->created_at)) }}</td>
                <td>{{ $invoice->type }}</td>
                <td>{{ $invoice->price }}</td>
                <td>{{ $invoice->payment_status }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
