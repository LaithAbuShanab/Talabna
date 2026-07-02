<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class OrderStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_the_order_and_who_changed_it(): void
    {
        $order = Order::factory()->create();
        $admin = User::factory()->admin()->create();

        $history = OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'status' => OrderStatus::Accepted,
            'changed_by_user_id' => $admin->id,
        ]);

        $this->assertTrue($history->order->is($order));
        $this->assertTrue($history->changedBy->is($admin));
        $this->assertSame(OrderStatus::Accepted, $history->status);
    }

    public function test_it_has_no_updated_at_column(): void
    {
        $history = OrderStatusHistory::factory()->create();

        $this->assertArrayNotHasKey('updated_at', $history->getAttributes());
    }

    public function test_updating_a_history_row_is_rejected(): void
    {
        $history = OrderStatusHistory::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('append-only');

        $history->note = 'trying to rewrite history';
        $history->save();
    }

    public function test_deleting_a_history_row_is_rejected(): void
    {
        $history = OrderStatusHistory::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('append-only');

        $history->delete();
    }

    public function test_multiple_transitions_accumulate_instead_of_replacing_each_other(): void
    {
        $order = Order::factory()->create();

        OrderStatusHistory::factory()->create(['order_id' => $order->id, 'status' => OrderStatus::Pending]);
        OrderStatusHistory::factory()->create(['order_id' => $order->id, 'status' => OrderStatus::Accepted]);
        OrderStatusHistory::factory()->create(['order_id' => $order->id, 'status' => OrderStatus::Preparing]);

        $this->assertCount(3, $order->statusHistories);
        $this->assertSame(
            [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Preparing],
            $order->statusHistories()->orderBy('id')->get()->pluck('status')->all(),
        );
    }
}
