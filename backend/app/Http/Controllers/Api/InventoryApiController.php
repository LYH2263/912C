<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Repositories\InventoryRepository;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryApiController extends Controller
{
    public function __construct(
        private InventoryRepository $repository,
        private InventoryService $service
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['category_id', 'low_stock', 'out_of_stock', 'sufficient']);
        $perPage = $request->get('per_page', 15);

        $inventory = $this->repository->paginate($filters, $perPage);

        return $this->paginated($inventory->items(), $inventory);
    }

    public function show(Product $product)
    {
        $product->load('category', 'inventoryLogs');
        return $this->success($product);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'remark' => 'nullable|string|max:500',
        ]);

        try {
            $log = $this->service->adjustStock($product, $validated['quantity'], $validated['remark'] ?? '');
            return $this->success($log, '库存更新成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
