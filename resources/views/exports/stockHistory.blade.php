<table>
    <thead>
        <tr>
            <th rowspan="3">Code</th>
            <th rowspan="3">Product Unit</th>
            @foreach ($dates as $date)
                <th colspan="{{ count($warehouses) * 2 }}">
                    <center>{{ $date }}</center>
                </th>
            @endforeach
        </tr>
        <tr>
            @foreach ($dates as $date)
                @foreach ($warehouses as $warehouse)
                    <th colspan="2">{{ $warehouse->name }}</th>
                @endforeach
            @endforeach
        </tr>
        <tr>
            @foreach ($dates as $date)
                @foreach ($warehouses as $warehouse)
                    <th>+</th>
                    <th>-</th>
                @endforeach
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($productUnits as $productUnit)
            <tr>
                <td>{{ $productUnit->code }}</td>
                <td>{{ $productUnit->name }}</td>
                @foreach ($dates as $date)
                    @foreach ($warehouses as $warehouse)
                        @php
                            $product = \App\Models\StockProductUnit::select('id')
                                ->where('warehouse_id', $warehouse->id)
                                ->where('product_unit_id', $productUnit->id)
                                ->withSum(
                                    [
                                        'stockHistories as increment' => fn($q) => $q
                                            ->where('is_increment', 1)
                                            ->whereDate('created_at', $date)
                                            ->where(
                                                fn($q) => $q
                                                    ->where('model_type', 'App\\Models\\AdjustmentRequest')
                                                    ->orWhere('description', 'like', '%import stock%'),
                                            ),
                                    ],
                                    'value',
                                )
                                ->withSum(
                                    [
                                        'stockHistories as decrement' => fn($q) => $q
                                            ->where('is_increment', 0)
                                            ->whereDate('created_at', $date)
                                            ->where(
                                                fn($q) => $q
                                                    ->where('model_type', 'App\\Models\\AdjustmentRequest')
                                                    ->orWhere('description', 'like', '%import stock%'),
                                            ),
                                    ],
                                    'value',
                                )
                                ->first();
                        @endphp
                        <td>{{ $product->increment }}</td>
                        <td>{{ $product->decrement }}</td>
                    @endforeach
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
