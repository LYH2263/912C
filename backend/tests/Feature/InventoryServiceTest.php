<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\InventoryLog;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InventoryService();
        $this->operator = User::factory()->create();
        $this->actingAs($this->operator);
    }

    /** @test */
    public function decrease_stock_throws_exception_when_insufficient_and_stock_unchanged()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('库存不足');

        try {
            $this->service->decreaseStock($product, 10);
        } finally {
            $product->refresh();
            $this->assertEquals(5, $product->stock_quantity);
            $this->assertCount(0, InventoryLog::where('product_id', $product->id)->get());
        }
    }

    /** @test */
    public function adjust_stock_rejects_negative_quantity()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('库存数量不能为负数');

        try {
            $this->service->adjustStock($product, -5);
        } finally {
            $product->refresh();
            $this->assertEquals(10, $product->stock_quantity);
            $this->assertCount(0, InventoryLog::where('product_id', $product->id)->get());
        }
    }

    /** @test */
    public function adjust_stock_restores_status_to_active_when_restocking_from_zero()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 0,
            'status' => 'sold_out',
        ]);

        $log = $this->service->adjustStock($product, 50, '补货');

        $product->refresh();
        $this->assertEquals('active', $product->status);
        $this->assertEquals(50, $product->stock_quantity);

        $this->assertInstanceOf(InventoryLog::class, $log);
        $this->assertEquals('adjust', $log->type);
        $this->assertEquals(50, $log->quantity);
        $this->assertEquals(0, $log->before_quantity);
        $this->assertEquals(50, $log->after_quantity);
        $this->assertEquals($this->operator->id, $log->operator_id);
    }

    /** @test */
    public function two_consecutive_decrease_stock_creates_two_logs_with_correct_quantity_signs()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 100,
            'status' => 'active',
        ]);

        $log1 = $this->service->decreaseStock($product, 20, null, '第一次出库');
        $product->refresh();
        $log2 = $this->service->decreaseStock($product, 30, null, '第二次出库');

        $logs = InventoryLog::where('product_id', $product->id)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(2, $logs);

        $this->assertEquals('out', $logs[0]->type);
        $this->assertEquals(-20, $logs[0]->quantity);
        $this->assertEquals(100, $logs[0]->before_quantity);
        $this->assertEquals(80, $logs[0]->after_quantity);
        $this->assertEquals($this->operator->id, $logs[0]->operator_id);

        $this->assertEquals('out', $logs[1]->type);
        $this->assertEquals(-30, $logs[1]->quantity);
        $this->assertEquals(80, $logs[1]->before_quantity);
        $this->assertEquals(50, $logs[1]->after_quantity);
        $this->assertEquals($this->operator->id, $logs[1]->operator_id);

        $product->refresh();
        $this->assertEquals(50, $product->stock_quantity);
    }

    /** @test */
    public function decrease_stock_sets_sold_out_status_when_stock_reaches_zero()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $this->service->decreaseStock($product, 10);

        $product->refresh();
        $this->assertEquals(0, $product->stock_quantity);
        $this->assertEquals('sold_out', $product->status);
    }

    /** @test */
    public function increase_stock_creates_log_with_correct_operator_id()
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $log = $this->service->increaseStock($product, 20, null, '入库测试');

        $this->assertInstanceOf(InventoryLog::class, $log);
        $this->assertEquals('in', $log->type);
        $this->assertEquals(20, $log->quantity);
        $this->assertEquals(10, $log->before_quantity);
        $this->assertEquals(30, $log->after_quantity);
        $this->assertEquals($this->operator->id, $log->operator_id);

        $product->refresh();
        $this->assertEquals(30, $product->stock_quantity);
    }
}
