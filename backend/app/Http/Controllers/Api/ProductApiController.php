<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    public function __construct(
        private ProductService $service,
        private \App\Repositories\ProductRepository $repository
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['category_id', 'status', 'search']);
        $perPage = $request->get('per_page', 15);

        $products = $this->repository->paginate($filters, $perPage);

        return $this->paginated(
            ProductResource::collection($products->items()),
            $products
        );
    }

    public function show(Product $product)
    {
        $product->load('category', 'inventoryLogs');
        return $this->success(new ProductResource($product));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'sku' => 'required|string|max:100|unique:products,sku',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|string|max:255',
            'images' => 'nullable|array',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
        ]);

        try {
            $product = $this->service->create($validated);
            return $this->success(new ProductResource($product), '商品创建成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'sku' => 'sometimes|string|max:100|unique:products,sku,' . $product->id,
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|string|max:255',
            'images' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive,sold_out',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
        ]);

        try {
            $product = $this->service->update($product, $validated);
            return $this->success(new ProductResource($product), '商品更新成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function destroy(Product $product)
    {
        try {
            $this->service->delete($product);
            return $this->success(null, '商品删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
