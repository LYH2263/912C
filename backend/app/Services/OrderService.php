<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\OrderRepository;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        public OrderRepository $repository,
        private InventoryService $inventoryService
    ) {
    }

    /**
     * 创建订单
     */
    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'];
            $products = $this->loadProducts($items);

            $this->validateProducts($products, $items);

            $orderNo = Order::generateOrderNo();
            $totalAmount = $this->calculateTotalAmount($products, $items);
            $discountAmount = $data['discount_amount'] ?? 0;
            $finalAmount = $totalAmount - $discountAmount;

            $order = $this->repository->create([
                'order_no' => $orderNo,
                'user_id' => $data['user_id'] ?? null,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'status' => 'pending',
                'shipping_address' => $data['shipping_address'] ?? null,
                'shipping_name' => $data['shipping_name'] ?? null,
                'shipping_phone' => $data['shipping_phone'] ?? null,
                'remark' => $data['remark'] ?? null,
            ]);

            $this->createOrderItems($order, $products, $items);

            Log::info('订单创建成功', ['order_id' => $order->id, 'order_no' => $order->order_no]);

            return $order->load('orderItems');
        });
    }

    /**
     * 一次性加载所有商品并按 ID 索引
     */
    private function loadProducts(array $items): \Illuminate\Database\Eloquent\Collection
    {
        $productIds = array_column($items, 'product_id');
        return Product::whereIn('id', $productIds)->get()->keyBy('id');
    }

    /**
     * 验证商品库存和状态
     */
    private function validateProducts(\Illuminate\Database\Eloquent\Collection $products, array $items): void
    {
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (!$product) {
                throw new \Exception("商品 ID: {$item['product_id']} 不存在");
            }
            if ($product->status !== 'active') {
                throw new \Exception("商品 {$product->name} 已下架，无法购买");
            }
            if (!$product->hasEnoughStock($item['quantity'])) {
                throw new \Exception("商品 {$product->name} 库存不足，当前库存：{$product->stock_quantity}");
            }
        }
    }

    /**
     * 计算订单总金额
     */
    private function calculateTotalAmount(\Illuminate\Database\Eloquent\Collection $products, array $items): float
    {
        $totalAmount = 0;
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $subtotal = $product->price * $item['quantity'];
            $totalAmount += $subtotal;
        }
        return $totalAmount;
    }

    /**
     * 创建订单项并扣减库存
     */
    private function createOrderItems(Order $order, \Illuminate\Database\Eloquent\Collection $products, array $items): void
    {
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $subtotal = $product->price * $item['quantity'];

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'product_price' => $product->price,
                'quantity' => $item['quantity'],
                'subtotal' => $subtotal,
            ]);

            $this->inventoryService->decreaseStock($product, $item['quantity'], $order->id, '订单创建');
        }
    }

    /**
     * 更新订单状态
     */
    public function updateStatus(Order $order, string $status): Order
    {
        $oldStatus = $order->status;

        // 状态流转验证
        $allowedTransitions = [
            'pending' => ['paid', 'cancelled'],
            'paid' => ['shipped', 'cancelled'],
            'shipped' => ['completed', 'cancelled'],
        ];

        if (!in_array($status, $allowedTransitions[$oldStatus] ?? [])) {
            throw new \Exception("订单状态不能从 {$oldStatus} 直接变更为 {$status}");
        }

        $updateData = ['status' => $status];

        // 记录状态变更时间
        switch ($status) {
            case 'paid':
                $updateData['paid_at'] = now();
                break;
            case 'shipped':
                $updateData['shipped_at'] = now();
                break;
            case 'completed':
                $updateData['completed_at'] = now();
                break;
            case 'cancelled':
                $updateData['cancelled_at'] = now();
                // 恢复库存
                $this->restoreInventory($order);
                break;
        }

        $order = $this->repository->update($order, $updateData);

        Log::info('订单状态更新', [
            'order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $status,
        ]);

        return $order;
    }

    /**
     * 恢复库存（订单取消时）
     */
    private function restoreInventory(Order $order): void
    {
        $order->load('orderItems.product');
        foreach ($order->orderItems as $item) {
            $this->inventoryService->increaseStock($item->product, $item->quantity, $order->id, '订单取消');
        }
    }
}
