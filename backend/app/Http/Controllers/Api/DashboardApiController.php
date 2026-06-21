<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatisticsService;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function __construct(
        private StatisticsService $service
    ) {
    }

    public function summary()
    {
        $data = $this->service->getDashboardSummary();
        return $this->success($data);
    }

    public function charts(Request $request)
    {
        $days = $request->get('days', 7);
        
        $orderTrends = $this->service->getOrderTrends($days);
        $productSales = $this->service->getProductSalesRanking(10);

        return $this->success([
            'order_trends' => $orderTrends,
            'product_sales' => $productSales,
        ]);
    }
}
