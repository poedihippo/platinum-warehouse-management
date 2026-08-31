<?php

namespace Tests\Unit\Pipes;

use App\Models\SalesOrder;
use App\Pipes\Order\CalculateAutoDiscount;
use Tests\TestCase;

class CalculateAutoDiscountTest extends TestCase
{
    private function runPipe(SalesOrder $salesOrder): SalesOrder
    {
        $pipe = new CalculateAutoDiscount();

        return $pipe->handle($salesOrder, fn (SalesOrder $order) => $order);
    }

    private function makeOrder(int $price): SalesOrder
    {
        return SalesOrder::make([
            'raw_source' => [],
            'price' => $price,
        ]);
    }

    public function test_it_applies_single_tier_discount(): void
    {
        $order = $this->runPipe($this->makeOrder(100000));

        $this->assertSame(95000, $order->price);
        $this->assertSame(5.0, $order->auto_discount);
        $this->assertSame(5000, $order->raw_source['auto_discount_nominal']);
        $this->assertSame([
            [
                'percent' => 5,
                'price_before' => 100000,
                'discount_nominal' => 5000,
            ],
        ], $order->raw_source['auto_discount_details']);

        $this->assertSame($order->raw_source['auto_discount_details'], $order->auto_discount_details);
    }

    public function test_it_applies_double_tier_discount(): void
    {
        $order = $this->runPipe($this->makeOrder(260000));

        $this->assertSame(242060, $order->price);
        $this->assertSame(6.9, $order->auto_discount);
        $this->assertSame(17940, $order->raw_source['auto_discount_nominal']);
        $this->assertSame([
            [
                'percent' => 5,
                'price_before' => 260000,
                'discount_nominal' => 13000,
            ],
            [
                'percent' => 2,
                'price_before' => 247000,
                'discount_nominal' => 4940,
            ],
        ], $order->raw_source['auto_discount_details']);
    }

    public function test_it_returns_empty_details_when_no_discount_matches(): void
    {
        $order = $this->runPipe($this->makeOrder(10000));

        $this->assertSame(10000, $order->price);
        $this->assertSame(0.0, $order->auto_discount);
        $this->assertSame([], $order->raw_source['auto_discount_details']);
        $this->assertSame(0, $order->raw_source['auto_discount_nominal']);
    }
}
