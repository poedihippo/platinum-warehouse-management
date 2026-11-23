<table>
    <thead>
        <tr>
            <th>Code</th>
            <th>Product Unit</th>
            <th>Price</th>
            @foreach ($warehouses as $warehouse)
                <th>{{ $warehouse->name }}</th>
            @endforeach
            <th>Multiply</th>
            @foreach ($warehouses as $warehouse)
                <th>{{ $warehouse->name }} (Accurate)</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($productUnits as $productUnit)
            @php
                $stocksCounts = [];
                $multiplyQty = max(1, (int) $productUnit->multiply_qty);
            @endphp
            <tr>
                <td>{{ $productUnit->code }}</td>
                <td>{{ $productUnit->name }}</td>
                <td>{{ $productUnit->price }}</td>
                @foreach ($warehouses as $warehouse)
                    @php
                        $stock = \App\Models\StockProductUnit::where('warehouse_id', $warehouse->id)
                            ->where('product_unit_id', $productUnit->id)
                            ->withCount(['stocks' => fn($q) => $q->whereAvailableStock()->whereNull('description')])
                            ->first(['product_unit_id', 'warehouse_id']);
                        $stocksCounts[$warehouse->id] = $stock?->stocks_count;
                    @endphp
                    <td>{{ $stock->stocks_count }}</td>
                @endforeach
                <td>{{ $multiplyQty }}</td>
                @foreach ($warehouses as $warehouse)
                    <td>{{ is_null($stocksCounts[$warehouse->id]) ? '' : round($stocksCounts[$warehouse->id] / $multiplyQty, 2) }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
