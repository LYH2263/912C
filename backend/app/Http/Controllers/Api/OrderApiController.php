<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderApiController extends Controller
{
    public function __construct(
        private OrderService $service,
        private \App\Repositories\OrderRepository $repository
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['status', 'order_no', 'start_date', 'end_date']);
        $perPage = $request->get('per_page', 15);

        $orders = $this->repository->paginate($filters, $perPage);

        return $this->paginated(
            OrderResource::collection($orders->items()),
            $orders
        );
    }

    public function show(Order $order)
    {
        $order->load('orderItems.product');
        return $this->success(new OrderResource($order));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_address' => 'nullable|string',
            'shipping_name' => 'nullable|string|max:100',
            'shipping_phone' => 'nullable|string|max:20',
            'remark' => 'nullable|string',
        ]);

        try {
            $order = $this->service->create($validated);
            return $this->success(new OrderResource($order), '订单创建成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,paid,shipped,completed,cancelled',
        ]);

        try {
            $order = $this->service->updateStatus($order, $validated['status']);
            return $this->success(new OrderResource($order), '订单状态更新成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
