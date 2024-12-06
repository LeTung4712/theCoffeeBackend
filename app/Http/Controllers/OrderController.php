<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thành công', 'order' => $order], 200);
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
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thành công', 'order' => $order], 200);
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
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thành công', 'order' => $order], 200);
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
            return response()->json(['message' => 'Cập nhật trạng thái đơn hàng thành công', 'order' => $order], 200);
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
            // Chuyển đổi topping_details từ chuỗi JSON thành mảng
            foreach ($order->orderItems as $item) {
                $item->topping_items = json_decode($item->topping_items, true);
            }
        }

        return $orders-> isEmpty() 
            ? response()->json(['message' => 'Không có lịch sử đơn hàng'], 404)
            : response()->json(['message' => 'Lấy lịch sử đơn hàng thành công', 'orders' => $orders], 200);

    }

    //lay thong tin don hang theo id
    public function getOrderInfo(Request $request)
    {
        $order = Order::where('id', $request->order_id)->with('orderItems')->first();
        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Chuyển đổi topping_details từ chuỗi JSON thành mảng
        foreach ($order->orderItems as $item) {
            $item->topping_details = json_decode($item->topping_details, true);
        }

        return $order 
            ? response()->json(['message' => 'Lấy thông tin đơn hàng thành công', 'order' => $order], 200)
            : response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
    }

    //lay don hang thanh cong
    public function getSuccessOrders()
    {
        $orders = Order::where('status', '3')
            ->orderby('id')
            ->with('orderItems') // dùng eager loading để lấy thông tin chi tiết của đơn hàng
            ->get();

        foreach ($orders as $order) {
            // Chuyển đổi topping_details từ chuỗi JSON thành mảng
            foreach ($order->orderItems as $item) {
                $item->topping_details = json_decode($item->topping_details, true);
            }
        }
        return $orders->isEmpty() 
            ? response()->json(['message' => 'Không tìm thấy đơn hàng'], 404)
            : response()->json(['message' => 'Lấy thông tin đơn hàng thành công', 'orders' => $orders], 200);
    }

    //lay don hang chua thanh cong
    public function getUnsuccessOrders()
    {
        //lấy tất cả đơn hàng có trạng thái khác 3 (đã giao thành công) và khác -1 (đã hủy)
        $orders = Order::where('status', '!=', '3')
            ->where('status', '!=', '-1')
            ->orderby('id')
            ->with('orderItems') // dùng eager loading để lấy thông tin chi tiết của đơn hàng
            ->get();
            
        foreach ($orders as $order) {
            // Chuyển đổi topping_details từ chuỗi JSON thành mảng
            foreach ($order->orderItems as $item) {
                $item->topping_details = json_decode($item->topping_details, true);
            }
        }

        return $orders->isEmpty() 
            ? response()->json(['message' => 'Không tìm thấy đơn hàng'], 404)
            : response()->json(['message' => 'Lấy thông tin đơn hàng thành công', 'orders' => $orders], 200);
    }

}
