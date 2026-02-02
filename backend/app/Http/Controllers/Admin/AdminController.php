<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function dashboardStats(Request $request)
    {
        // Date range filters
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth());

        // Total statistics
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
        $totalRevenue = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'paid')
            ->sum('total');
        $totalProducts = Product::count();
        $totalCustomers = User::where('created_at', '>=', $startDate)->count();

        // Recent orders
        $recentOrders = Order::with(['user', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Top selling products
        $topProducts = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.id', 'products.name', 'products.sku',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.total) as revenue')
            )
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('total_sold', 'desc')
            ->limit(10)
            ->get();

        // Sales chart data (last 30 days)
        $salesData = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total) as revenue')
            )
            ->whereBetween('created_at', [Carbon::now()->subDays(30), Carbon::now()])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Category distribution
        $categorySales = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->groupBy('categories.id', 'categories.name')
            ->get();

        return response()->json([
            'stats' => [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'total_products' => $totalProducts,
                'total_customers' => $totalCustomers,
                'conversion_rate' => $this->calculateConversionRate($startDate, $endDate),
                'average_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0
            ],
            'recent_orders' => $recentOrders,
            'top_products' => $topProducts,
            'sales_data' => $salesData,
            'category_sales' => $categorySales,
            'order_status_distribution' => $this->getOrderStatusDistribution($startDate, $endDate)
        ]);
    }

    private function calculateConversionRate($startDate, $endDate)
    {
        $totalVisitors = 1000; // This should come from analytics
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
        
        return $totalVisitors > 0 ? ($totalOrders / $totalVisitors) * 100 : 0;
    }

    private function getOrderStatusDistribution($startDate, $endDate)
    {
        return Order::whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');
    }

    public function salesReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'report_type' => 'required|in:daily,weekly,monthly'
        ]);

        $query = Order::whereBetween('created_at', [$request->start_date, $request->end_date])
            ->where('payment_status', 'paid');

        switch ($request->report_type) {
            case 'daily':
                $query->select(
                    DB::raw('DATE(created_at) as period'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('AVG(total) as avg_order_value')
                )->groupBy('period');
                break;
            case 'weekly':
                $query->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('WEEK(created_at) as week'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('AVG(total) as avg_order_value')
                )->groupBy('year', 'week');
                break;
            case 'monthly':
                $query->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('AVG(total) as avg_order_value')
                )->groupBy('year', 'month');
                break;
        }

        $report = $query->orderBy('period')->get();

        // Additional metrics
        $metrics = [
            'total_revenue' => $report->sum('revenue'),
            'total_orders' => $report->sum('orders'),
            'average_order_value' => $report->avg('avg_order_value'),
            'best_performing_period' => $report->sortByDesc('revenue')->first(),
            'growth_rate' => $this->calculateGrowthRate($report)
        ];

        return response()->json([
            'report' => $report,
            'metrics' => $metrics,
            'product_performance' => $this->getProductPerformance($request->start_date, $request->end_date),
            'category_performance' => $this->getCategoryPerformance($request->start_date, $request->end_date)
        ]);
    }

    private function calculateGrowthRate($report)
    {
        if ($report->count() < 2) return 0;
        
        $first = $report->first()->revenue;
        $last = $report->last()->revenue;
        
        return $first > 0 ? (($last - $first) / $first) * 100 : 0;
    }

    private function getProductPerformance($startDate, $endDate)
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(order_items.quantity) as units_sold'),
                DB::raw('SUM(order_items.total) as revenue'),
                DB::raw('AVG(order_items.price) as avg_price')
            )
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('revenue', 'desc')
            ->limit(20)
            ->get();
    }

    private function getCategoryPerformance($startDate, $endDate)
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders'),
                DB::raw('SUM(order_items.quantity) as units_sold'),
                DB::raw('SUM(order_items.total) as revenue')
            )
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('revenue', 'desc')
            ->get();
    }

    public function inventoryReport()
    {
        $inventory = Product::with(['category', 'brand'])
            ->select('id', 'name', 'sku', 'stock', 'price', 'category_id', 'brand_id')
            ->orderBy('stock', 'asc')
            ->get();

        $lowStock = $inventory->where('stock', '<', 10);
        $outOfStock = $inventory->where('stock', 0);

        $metrics = [
            'total_products' => $inventory->count(),
            'total_stock_value' => $inventory->sum(function($product) {
                return $product->stock * $product->price;
            }),
            'low_stock_count' => $lowStock->count(),
            'out_of_stock_count' => $outOfStock->count(),
            'average_stock' => $inventory->avg('stock')
        ];

        return response()->json([
            'inventory' => $inventory,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'metrics' => $metrics
        ]);
    }

    public function customerReport()
    {
        $customers = User::withCount(['orders' => function($query) {
                $query->where('payment_status', 'paid');
            }])
            ->withSum(['orders' => function($query) {
                $query->where('payment_status', 'paid');
            }], 'total')
            ->orderBy('orders_sum_total', 'desc')
            ->limit(50)
            ->get();

        $metrics = [
            'total_customers' => User::count(),
            'new_customers_today' => User::whereDate('created_at', today())->count(),
            'new_customers_this_month' => User::whereMonth('created_at', now()->month)->count(),
            'active_customers' => User::whereHas('orders', function($query) {
                $query->where('created_at', '>=', now()->subDays(30));
            })->count(),
            'average_order_value' => $customers->avg('orders_sum_total')
        ];

        return response()->json([
            'customers' => $customers,
            'metrics' => $metrics,
            'customer_acquisition' => $this->getCustomerAcquisitionData()
        ]);
    }

    private function getCustomerAcquisitionData()
    {
        return User::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as customers')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    public function exportReport(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:sales,inventory,customers',
            'format' => 'required|in:csv,excel,pdf',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date'
        ]);

        // This would generate and return a file
        // For now, return a placeholder response
        
        return response()->json([
            'message' => 'Report export initiated',
            'download_url' => '/api/admin/reports/download/temp-file.' . $request->format
        ]);
    }
}