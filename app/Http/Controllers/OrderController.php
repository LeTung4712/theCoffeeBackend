<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin',
            [
                'except' =>
                [
                    'addOrder',
                    'getOrderHistory',
                    'getOrderInfo',
                    'paidOrder',
                    'acceptOrder',
                    'successOrder',
                    'cancelOrder',
                    'getSuccessOrders',
                    'getUnsuccessOrders',
                    'getDeliveryOrders',
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

    //them don hang
    public function addOrder(Request $request)
    {
        try {
            // Tạo đơn hàng trước nhưng chưa lưu
            $order = new Order([
                'user_id' => (int) $request->user_id,
                'user_name' => (string) $request->user_name,
                'mobile_no' => (string) $request->mobile_no,
                'address' => (string) $request->address,
                'note' => (string) $request->note,
                'shipping_fee' => (float) $request->shipping_fee,
                'total_price' => (float) $request->total_price,
                'discount_amount' => (float) $request->discount_amount,
                'final_price' => (float) $request->final_price,
                'payment_method' => (string) $request->payment_method,
                'status' => '0',
                'order_time' => Carbon::now(),
            ]);

            // Gán giá trị cho order_id trước khi lưu
            $order->order_code = "TCH" . time() . "" . $order->id; // Lưu ý: order->id sẽ là null tại thời điểm này
            $order->save(); // Lưu đơn hàng vào cơ sở dữ liệu
            foreach ($request->products as $product) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => (int) $product['product_id'],
                    'product_name' => (string) $product['product_name'],
                    'product_price' => (float) $product['product_price'],
                    'product_quantity' => (int) $product['product_quantity'],
                    'topping_items' => json_encode($product['topping_items']),
                    'size' => (string) $product['size'],
                    'item_note' => (string) $product['item_note'],
                ]);
            }

            return response()->json(['message' => 'Đặt hàng thành công', 'order_code' => $order->order_code], 200);
        } catch (\Exception $err) {
            return response()->json(['message' => 'Đặt hàng thất bại', 'error' => $err->getMessage(), 'order_code' => null], 400);
        }
    }

    //don hang da thanh toan
    public function paidOrder(Request $request)
    {
        $order = Order::where('id', $request->order_id)->first();
        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }
        try {
            $order->status = '1';
            $order->save();
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thất bại', 'error' => $e->getMessage()], 400);
        }
    }

    //dong y giao don hang
    public function acceptOrder(Request $request)
    {
        $order = Order::where('id', $request->order_id)->first();
        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }
        try {
            $order->status = '2';
            $order->save();
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thất bại', 'error' => $e->getMessage()], 400);
        }
    }

    //đơn hàng đã giao thành công
    public function successOrder(Request $request)
    {
        $order = Order::where('id', $request->order_id)->first();
        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }
        try {
            $order->status = '3';
            $order->save();
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thất bại', 'error' => $e->getMessage()], 400);
        }
    }

    //huy don hang
    public function cancelOrder(Request $request)
    {
        $order = Order::where('id', $request->order_id)->first();
        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }
        try {
            $order->status = '-1';
            $order->save();
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thất bại', 'error' => $e->getMessage()], 400);
        }
    }

    //xem cac don hang cua user
    public function getOrderHistory(Request $request)
    {
        $orders = Order::where('user_id', $request->user_id)
            ->orderby('id', 'desc')
            ->with('orderItems') // dùng eager loading để lấy thông tin chi tiết của đơn hàng
            ->get();

        foreach ($orders as $order) {
            // Chuyển đổi topping_items từ chuỗi JSON thành mảng
            foreach ($order->orderItems as $item) {
                $item->topping_items = json_decode($item->topping_items, true);
            }

            // Chuyển đổi các giá trị số sang dạng số
            $order->total_price = (float) $order->total_price;
            $order->discount_amount = (float) $order->discount_amount;
            $order->shipping_fee = (float) $order->shipping_fee;
            $order->final_price = (float) $order->final_price;
        }

        return $orders->isEmpty()
        ? response()->json(['message' => 'Không có lịch sử đơn hàng'], 404)
        : response()->json(['message' => 'Lấy lịch sử đơn hàng thành công', 'orders' => $orders], 200);
    }

    //lay thong tin don hang theo id
    public function getOrderInfo(Request $request)
    {
        $order = Order::where('order_code', $request->order_code)->with('orderItems')->first();
        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Chuyển đổi topping_items từ chuỗi JSON thành mảng
        foreach ($order->orderItems as $item) {
            $item->product_price = (float) $item->product_price;
            $item->topping_items = $item->topping_items ? json_decode($item->topping_items, true) : [];
        }
        // Chuyển đổi các giá trị số sang dạng số
        $order->total_price = (float) $order->total_price;
        $order->discount_amount = (float) $order->discount_amount;
        $order->shipping_fee = (float) $order->shipping_fee;
        $order->final_price = (float) $order->final_price;

        return $order
        ? response()->json(['message' => 'Lấy thông tin đơn hàng thành công', 'order' => $order], 200)
        : response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
    }

    //lay don hang hoan thanh
    public function getSuccessOrders()
    {
        $orders = Order::where('status', '3')
            ->orWhere('status', '-1')
            ->orderby('id')
            ->with('orderItems') // dùng eager loading để lấy thông tin chi tiết của đơn hàng
            ->get();

        foreach ($orders as $order) {
            // Chuyển đổi topping_items từ chuỗi JSON thành mảng
            foreach ($order->orderItems as $item) {
                $item->product_price = (float) $item->product_price;
                $item->topping_items = json_decode($item->topping_items, true);
            }
            // Chuyển đổi các giá trị số sang dạng số
            $order->total_price = (float) $order->total_price;
            $order->discount_amount = (float) $order->discount_amount;
            $order->shipping_fee = (float) $order->shipping_fee;
            $order->final_price = (float) $order->final_price;
        }
        return $orders->isEmpty()
        ? response()->json(['message' => 'Không tìm thấy đơn hàng'], 404)
        : response()->json(['message' => 'Lấy thông tin đơn hàng thành công', 'orders' => $orders], 200);
    }

    //lay don hang chua hoan thanh
    public function getUnsuccessOrders()
    {
        //lấy tất cả đơn hàng có trạng thái 0 (chờ xác nhận) và 1 (đã thanh toán chờ xác nhận)
        $orders = Order::where('status', '=', '0')
            ->orWhere('status', '=', '1')
            ->orderby('id')
            ->with('orderItems') // dùng eager loading để lấy thông tin chi tiết của đơn hàng
            ->get();

        foreach ($orders as $order) {
            // Chuyển đổi topping_items từ chuỗi JSON thành mảng
            foreach ($order->orderItems as $item) {
                $item->product_price = (float) $item->product_price;
                $item->topping_items = json_decode($item->topping_items, true);
            }
            // Chuyển đổi các giá trị số sang dạng số
            $order->total_price = (float) $order->total_price;
            $order->discount_amount = (float) $order->discount_amount;
            $order->shipping_fee = (float) $order->shipping_fee;
            $order->final_price = (float) $order->final_price;
        }

        return $orders->isEmpty()
        ? response()->json(['message' => 'Không tìm thấy đơn hàng'], 404)
        : response()->json(['message' => 'Lấy thông tin đơn hàng thành công', 'orders' => $orders], 200);
    }

    //lay don hang đang giao
    public function getDeliveryOrders()
    {
        $orders = Order::where('status', '2')
            ->orderby('id')
            ->with('orderItems') // dùng eager loading để lấy thông tin chi tiết của đơn hàng
            ->get();

        foreach ($orders as $order) {
            // Chuyển đổi topping_items từ chuỗi JSON thành mảng
            foreach ($order->orderItems as $item) {
                $item->product_price = (float) $item->product_price;
                $item->topping_items = json_decode($item->topping_items, true);
            }
            // Chuyển đổi các giá trị số sang dạng số
            $order->total_price = (float) $order->total_price;
            $order->discount_amount = (float) $order->discount_amount;
            $order->shipping_fee = (float) $order->shipping_fee;
            $order->final_price = (float) $order->final_price;
        }
        return $orders->isEmpty()
        ? response()->json(['message' => 'Không tìm thấy đơn hàng'], 404)
        : response()->json(['message' => 'Lấy thông tin đơn hàng thành công', 'orders' => $orders], 200);
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
            'newCustomers' => $orders->unique('user_id')->count()
        ];

        // Tính tỷ lệ tăng trưởng
        $previousStats = [
            'revenue' => (float) $previousOrders->sum('final_price'),
            'orders' => $previousOrders->count(),
            'customers' => $previousOrders->distinct('user_id')->count('user_id')
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
                'canceled' => $stats['canceledOrders']
            ],
            'paymentMethodByTimeRange' => [
                'COD' => $stats['paymentCOD'],
                'MoMo' => $stats['paymentMoMo'],
                'Zalopay' => $stats['paymentZalopay'],
                'VNPay' => $stats['paymentVNPay']
            ]
        ], 200);
    }

    private function getStartDate($timeRange)
    {
        $startDate = now();
        switch ($timeRange) {
            case 'month': return $startDate->subMonth();
            case 'quarter': return $startDate->subQuarter();
            case 'year': return $startDate->subYear();
            default: return $startDate->subWeek();
        }
    }

    private function calculateGrowthRates($current, $previous)
    {
        return [
            'revenue' => $this->calculateGrowthRate($current['totalRevenue'], $previous['revenue']),
            'orders' => $this->calculateGrowthRate($current['totalOrders'], $previous['orders']),
            'customers' => $this->calculateGrowthRate($current['newCustomers'], $previous['customers'])
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
                    $revenueData[] = $monthRevenue ? $monthRevenue->total_revenue : 0;
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
                    $revenueData[] = $dayRevenue ? $dayRevenue->total_revenue : 0;
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
                    $revenueData[] = $dayRevenue ? $dayRevenue->total_revenue : 0;
                }
                break;
        }

        return [
            'labels' => $labels,
            'revenueData' => $revenueData,
        ];
    }

    
}
