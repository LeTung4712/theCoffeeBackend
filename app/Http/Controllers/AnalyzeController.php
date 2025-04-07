<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyzeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin',
            [
                'except' =>
                [
                    'getAnalyzeOrders',
                ],
            ]
        );
        if (!auth('admin')->check()) { //
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }
    }

    //thong ke
    public function getAnalyzeOrders(Request $request)
    {
        $timeRange = $request->query('timeRange', 'week');
        $startDate = $this->getStartDate($timeRange);
        $endDate = now();

        // Lấy dữ liệu đơn hàng trong khoảng thời gian
        $orders = Order::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->get();

        // Lấy dữ liệu đơn hàng trước khoảng thời gian này
        $previousOrders = Order::where('created_at', '<', $startDate);

        // Tính toán các chỉ số thống kê
        $stats = [
            'totalRevenue' => (float) $orders->sum('final_price'),
            'totalOrders' => $orders->count(),
            'completedOrders' => $orders->where('status', '3')->count(),
            'pendingOrders' => $orders->whereIn('status', ['0', '1', '2'])->count(),
            'canceledOrders' => $orders->where('status', '-1')->count(),
            'paymentCOD' => $orders->where('payment_method', 'cod')->count(),
            'paymentMoMo' => $orders->where('payment_method', 'momo')->count(),
            'paymentZalopay' => $orders->where('payment_method', 'zalopay')->count(),
            'paymentVNPay' => $orders->where('payment_method', 'vnpay')->count(),
            'newCustomers' => $orders->unique('user_id')->count(),
        ];

        // Tính tỷ lệ tăng trưởng
        $previousStats = [
            'revenue' => (float) $previousOrders->sum('final_price'),
            'orders' => $previousOrders->count(),
            'customers' => $previousOrders->distinct('user_id')->count('user_id'),
        ];

        $growthRates = $this->calculateGrowthRates($stats, $previousStats);

        // Lấy top sản phẩm bán chạy
        $topProducts = $this->getTopProducts($startDate, $endDate);

        return response()->json([
            'totalRevenue' => $stats['totalRevenue'],
            'revenueGrowth' => $growthRates['revenue'],
            'totalOrders' => $stats['totalOrders'],
            'orderGrowth' => $growthRates['orders'],
            'completionRate' => $this->calculateCompletionRate($stats['completedOrders'], $stats['totalOrders']),
            'newCustomers' => $stats['newCustomers'],
            'customerGrowth' => $growthRates['customers'],
            'topProducts' => $topProducts,
            'revenueByTimeRange' => $this->getRevenueByTimeRange($timeRange),
            'orderStatusByTimeRange' => [
                'completed' => $stats['completedOrders'],
                'pending' => $stats['pendingOrders'],
                'canceled' => $stats['canceledOrders'],
            ],
            'paymentMethodByTimeRange' => [
                'COD' => $stats['paymentCOD'],
                'MoMo' => $stats['paymentMoMo'],
                'Zalopay' => $stats['paymentZalopay'],
                'VNPay' => $stats['paymentVNPay'],
            ],
        ], 200);
    }

    private function getStartDate($timeRange)
    {
        $startDate = now();
        switch ($timeRange) {
            case 'month':return $startDate->subMonth();
            case 'quarter':return $startDate->subQuarter();
            case 'year':return $startDate->subYear();
            default:return $startDate->subWeek();
        }
    }

    private function calculateGrowthRates($current, $previous)
    {
        return [
            'revenue' => $this->calculateGrowthRate($current['totalRevenue'], $previous['revenue']),
            'orders' => $this->calculateGrowthRate($current['totalOrders'], $previous['orders']),
            'customers' => $this->calculateGrowthRate($current['newCustomers'], $previous['customers']),
        ];
    }

    private function calculateGrowthRate($current, $previous)
    {
        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 2) : 0;
    }

    private function calculateCompletionRate($completed, $total)
    {
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    private function getTopProducts($startDate, $endDate)
    {
        return OrderItem::select('order_items.product_id', 'products.name', 'products.image_url')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->selectRaw('SUM(order_items.product_quantity) as soldCount, SUM(order_items.product_price * order_items.product_quantity) as revenue')
            ->groupBy('order_items.product_id', 'products.name', 'products.image_url')
            ->orderBy('soldCount', 'desc')
            ->take(5)
            ->get();
    }

    // Phương thức lấy doanh thu theo khoảng thời gian
    public function getRevenueByTimeRange($timeRange)
    {
        $endDate = now();
        $labels = [];
        $revenueData = [];
        $costData = [];
        $profitData = [];
        $costPercent = 0.3;

        switch ($timeRange) {
            case 'year':
                // Lấy 12 tháng từ tháng hiện tại trở về trước
                $startDate = now()->subMonths(11)->startOfMonth();
                $revenues = Order::where('created_at', '>=', $startDate)
                    ->where('created_at', '<=', $endDate)
                    ->selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, SUM(final_price) as total_revenue')
                    ->groupBy('year', 'month')
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get();

                // Tạo mảng với 12 tháng
                for ($i = 11; $i >= 0; $i--) {
                    $date = now()->subMonths($i);
                    $month = $date->format('m/Y');
                    $labels[] = $month;
                    $monthRevenue = $revenues->where('month', $date->month)
                        ->where('year', $date->year)
                        ->first();
                    $revenueData[] = $monthRevenue ? (float)$monthRevenue->total_revenue : 0;
                    $costData[] = $monthRevenue ? $costPercent * $monthRevenue->total_revenue : 0;
                    $profitData[] = $monthRevenue ? (1 - $costPercent) * $monthRevenue->total_revenue : 0;
                }
                break;

            case 'quarter':
                // Lấy 3 tháng từ tháng hiện tại trở về trước
                $startDate = now()->subMonths(2)->startOfMonth();
                $revenues = Order::where('created_at', '>=', $startDate)
                    ->where('created_at', '<=', $endDate)
                    ->selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, SUM(final_price) as total_revenue')
                    ->groupBy('year', 'month')
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get();

                // Tạo mảng với 3 tháng
                for ($i = 2; $i >= 0; $i--) {
                    $date = now()->subMonths($i);
                    $month = $date->format('m/Y');
                    $labels[] = $month;
                    $monthRevenue = $revenues->where('month', $date->month)
                        ->where('year', $date->year)
                        ->first();
                    $revenueData[] = $monthRevenue ? (float)$monthRevenue->total_revenue : 0;
                    $costData[] = $monthRevenue ? $costPercent * $monthRevenue->total_revenue : 0;
                    $profitData[] = $monthRevenue ? (1 - $costPercent) * $monthRevenue->total_revenue : 0;
                }
                break;

            case 'month':
                // Lấy 30 ngày gần nhất
                $startDate = now()->subDays(29)->startOfDay();
                $revenues = Order::where('created_at', '>=', $startDate)
                    ->where('created_at', '<=', $endDate)
                    ->selectRaw('DATE(created_at) as date, SUM(final_price) as total_revenue')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();

                // Tạo mảng với 30 ngày
                for ($i = 29; $i >= 0; $i--) {
                    $date = now()->subDays($i)->format('Y-m-d');
                    $labels[] = Carbon::parse($date)->format('d/m');
                    $dayRevenue = $revenues->where('date', $date)->first();
                    $revenueData[] = $dayRevenue ? (float)$dayRevenue->total_revenue : 0;
                    $costData[] = $dayRevenue ? $costPercent * $dayRevenue->total_revenue : 0;
                    $profitData[] = $dayRevenue ? (1 - $costPercent) * $dayRevenue->total_revenue : 0;
                }
                break;

            default: // 'week'
                // Lấy 7 ngày gần nhất
                $startDate = now()->subDays(6)->startOfDay();
                $revenues = Order::where('created_at', '>=', $startDate)
                    ->where('created_at', '<=', $endDate)
                    ->selectRaw('DATE(created_at) as date, SUM(final_price) as total_revenue')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();

                // Tạo mảng với 7 ngày
                for ($i = 6; $i >= 0; $i--) {
                    $date = now()->subDays($i)->format('Y-m-d');
                    $labels[] = Carbon::parse($date)->format('d/m');
                    $dayRevenue = $revenues->where('date', $date)->first();
                    $revenueData[] = $dayRevenue ? (float)$dayRevenue->total_revenue : 0;
                    $costData[] = $dayRevenue ? $costPercent * $dayRevenue->total_revenue : 0;
                    $profitData[] = $dayRevenue ? (1 - $costPercent) * $dayRevenue->total_revenue : 0;
                }
                break;
        }

        return [
            'labels' => $labels,
            'revenueData' => $revenueData,
            'costData' => $costData,
            'profitData' => $profitData,
        ];
    }
}
