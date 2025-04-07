<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
                ],
            ]
        );
        if (! auth('admin')->check()) { //
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }
    }

    //them don hang
    public function addOrder(Request $request)
    {
        // Validate dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'user_id'                     => 'required|exists:users,id',
            'user_name'                   => 'required|string|max:100',
            'mobile_no'                   => 'required|string|max:15',
            'address'                     => 'required|string',
            'payment_method'              => 'required|in:cod,vnpay,momo,zalopay',
            'products'                    => 'required|array|min:1',
            'products.*.product_id'       => 'required|exists:products,id',
            'products.*.product_quantity' => 'required|integer|min:1',
            'products.*.size'             => 'required|in:S,M,L',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Dữ liệu không hợp lệ', 'errors' => $validator->errors()], 422);
        }

        try {
            // Sử dụng transaction để đảm bảo tính toàn vẹn dữ liệu
            DB::beginTransaction();

            // Tạo mã đơn hàng duy nhất
            do {
                $orderCode = 'TCH' . time() . strtoupper(Str::random(6));
            } while (Order::where('order_code', $orderCode)->exists());

            // Tạo đơn hàng
            $order = new Order([
                'user_id'         => (int) $request->user_id,
                'user_name'       => (string) $request->user_name,
                'mobile_no'       => (string) $request->mobile_no,
                'address'         => (string) $request->address,
                'note'            => (string) $request->note ?? '',
                'shipping_fee'    => (float) $request->shipping_fee ?? 0,
                'total_price'     => (float) $request->total_price,
                'discount_amount' => (float) $request->discount_amount ?? 0,
                'final_price'     => (float) $request->final_price,
                'payment_method'  => (string) $request->payment_method,
                'payment_status'  => '0',
                'status'          => '0',
                'order_code'      => $orderCode,
                //'order_time'      => Carbon::now(),
            ]);

            // Kiểm tra lại final_price
            $calculatedFinalPrice = $order->total_price - $order->discount_amount + $order->shipping_fee;
            if (abs($calculatedFinalPrice - $order->final_price) > 0.01) {
                throw new \Exception('Tổng tiền đơn hàng không khớp với tính toán');
            }

            // Lưu đơn hàng (một lần duy nhất)
            $order->save();

            // Thêm các sản phẩm vào đơn hàng
            foreach ($request->products as $product) {
                // Kiểm tra sản phẩm tồn tại
                $productInfo = Product::find($product['product_id']);
                if (! $productInfo) {
                    throw new \Exception('Sản phẩm không tồn tại: ' . $product['product_id']);
                }

                OrderItem::create([
                    'order_id'         => $order->id,
                    'product_id'       => (int) $product['product_id'],
                    'product_name'     => (string) $product['product_name'] ?? $productInfo->name,
                    'product_price'    => (float) $product['product_price'] ?? $productInfo->price,
                    'product_quantity' => (int) $product['product_quantity'],
                    'topping_items'    => json_encode($product['topping_items'] ?? []),
                    'size'             => (string) $product['size'],
                    'item_note'        => (string) $product['item_note'] ?? '',
                ]);
            }

            // Nếu mọi thứ OK, commit transaction
            DB::commit();

            return response()->json([
                'message'    => 'Đặt hàng thành công',
                'order_code' => $order->order_code,
            ], 200);
        } catch (\Exception $err) {
            // Nếu có lỗi, rollback tất cả thay đổi
            DB::rollBack();
            return response()->json([
                'message'    => 'Đặt hàng thất bại',
                'error'      => $err->getMessage(),
                'order_code' => null,
            ], 400);
        }
    }

    //don hang da thanh toan
    public function paidOrder(Request $request)
    {
        $order = Order::where('id', $request->order_id)->first();
        if (! $order) {
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
        if (! $order) {
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
        if (! $order) {
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
        if (! $order) {
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
            $order->total_price     = (float) $order->total_price;
            $order->discount_amount = (float) $order->discount_amount;
            $order->shipping_fee    = (float) $order->shipping_fee;
            $order->final_price     = (float) $order->final_price;
        }

        return $orders->isEmpty()
        ? response()->json(['message' => 'Không có lịch sử đơn hàng'], 404)
        : response()->json(['message' => 'Lấy lịch sử đơn hàng thành công', 'orders' => $orders], 200);
    }

    //lay thong tin don hang theo id
    public function getOrderInfo(Request $request)
    {
        $order = Order::where('order_code', $request->order_code)->with('orderItems')->first();
        if (! $order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Chuyển đổi topping_items từ chuỗi JSON thành mảng
        foreach ($order->orderItems as $item) {
            $item->product_price = (float) $item->product_price;
            $item->topping_items = $item->topping_items ? json_decode($item->topping_items, true) : [];
        }
        // Chuyển đổi các giá trị số sang dạng số
        $order->total_price     = (float) $order->total_price;
        $order->discount_amount = (float) $order->discount_amount;
        $order->shipping_fee    = (float) $order->shipping_fee;
        $order->final_price     = (float) $order->final_price;

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
            $order->total_price     = (float) $order->total_price;
            $order->discount_amount = (float) $order->discount_amount;
            $order->shipping_fee    = (float) $order->shipping_fee;
            $order->final_price     = (float) $order->final_price;
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
            $order->total_price     = (float) $order->total_price;
            $order->discount_amount = (float) $order->discount_amount;
            $order->shipping_fee    = (float) $order->shipping_fee;
            $order->final_price     = (float) $order->final_price;
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
            $order->total_price     = (float) $order->total_price;
            $order->discount_amount = (float) $order->discount_amount;
            $order->shipping_fee    = (float) $order->shipping_fee;
            $order->final_price     = (float) $order->final_price;
        }
        return $orders->isEmpty()
        ? response()->json(['message' => 'Không tìm thấy đơn hàng'], 404)
        : response()->json(['message' => 'Lấy thông tin đơn hàng thành công', 'orders' => $orders], 200);
    }
}
