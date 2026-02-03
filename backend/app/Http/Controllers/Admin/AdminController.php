<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class AdminController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function dashboardStats(Request $request)
    {
        try {
            $stats = [
                'totalRevenue' => Order::where('status', 'completed')->sum('total_amount'),
                'totalOrders' => Order::count(),
                'totalProducts' => Product::count(),
                'totalCustomers' => User::where('role', 'customer')->count(),
                'conversionRate' => $this->calculateConversionRate(),
                'averageOrderValue' => Order::where('status', 'completed')->avg('total_amount') ?? 0,
            ];

            // Calculate trends (compare with previous period)
            $stats['revenueTrend'] = $this->calculateTrend('revenue');
            $stats['ordersTrend'] = $this->calculateTrend('orders');
            $stats['productsTrend'] = $this->calculateTrend('products');
            $stats['customersTrend'] = $this->calculateTrend('customers');

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales data for chart
     */
    public function getSalesData(Request $request)
    {
        try {
            $period = $request->get('period', 'month');
            
            $salesData = match($period) {
                'week' => $this->getWeeklySalesData(),
                'year' => $this->getYearlySalesData(),
                default => $this->getMonthlySalesData(),
            };

            return response()->json([
                'success' => true,
                'data' => $salesData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order status distribution
     */
    public function getOrderStatus(Request $request)
    {
        try {
            $orderStatusData = Order::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $orderStatusData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order status data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get category distribution for chart
     */
    public function getCategoryDistribution(Request $request)
    {
        try {
            $categoryData = Product::with('category')
                ->select('category_id', DB::raw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_products'))
                ->groupBy('category_id')
                ->with('category')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->category->name ?? 'Uncategorized',
                        'count' => $item->active_products
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $categoryData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch category distribution',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate conversion rate (mock implementation - customize as needed)
     */
    private function calculateConversionRate(): float
    {
        $totalVisitors = 10000; // This should come from analytics
        $totalConversions = Order::where('status', 'completed')->count();
        
        return $totalVisitors > 0 ? ($totalConversions / $totalVisitors) * 100 : 0;
    }

    /**
     * Calculate trend percentage (mock implementation - customize as needed)
     */
    private function calculateTrend(string $type): float
    {
        // Mock implementation - replace with actual calculation comparing current vs previous period
        $trends = [
            'revenue' => 12.5,
            'orders' => 8.2,
            'products' => 5.1,
            'customers' => 15.3,
        ];

        return $trends[$type] ?? 0;
    }

    /**
     * Get monthly sales data
     */
    private function getMonthlySalesData(): array
    {
        $labels = [];
        $data = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');
            
            $sales = Order::where('status', 'completed')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('total_amount');
            
            $data[] = $sales;
        }

        return compact('labels', 'data');
    }

    /**
     * Get weekly sales data
     */
    private function getWeeklySalesData(): array
    {
        $labels = [];
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('D, M j');
            
            $sales = Order::where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('total_amount');
            
            $data[] = $sales;
        }

        return compact('labels', 'data');
    }

    /**
     * Get yearly sales data
     */
    private function getYearlySalesData(): array
    {
        $labels = [];
        $data = [];

        for ($i = 5; $i >= 0; $i--) {
            $year = now()->subYears($i)->year;
            $labels[] = (string) $year;
            
            $sales = Order::where('status', 'completed')
                ->whereYear('created_at', $year)
                ->sum('total_amount');
            
            $data[] = $sales;
        }

        return compact('labels', 'data');
    }
}
