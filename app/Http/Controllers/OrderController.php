<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Topping;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    //tạo mã đơn hàng
    private function generateOrderCode($userId)
    {
        // timestamp dạng ymdHis: 20250612124530
        $timestamp = now()->format('YmdHis');

        // random 4 ký tự
        $rand = strtoupper(Str::random(4));

        // mã đơn: TCS-20250612-UID1-ABC4
        return "TCS-{$timestamp}-U{$userId}-{$rand}";
    }

    //them don hang
    public function addOrder(Request $request)
    {
        $user = auth('user')->user();

        // Validate dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'user_name'                   => 'required|string|max:100',
            'mobile_no'                   => ['required', 'regex:/^0[0-9]{9}$/', 'size:10'],
            'address'                     => 'required|string',
            'payment_method'              => 'required|in:cod,vnpay,momo,zalopay',
            'products'                    => 'required|array|min:1',
            'products.*.product_id'       => 'required|exists:products,id',
            'products.*.product_quantity' => 'required|integer|min:1',
            'products.*.size'             => 'required|in:S,M,L',
            'voucher_code'                => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            // Sử dụng transaction để đảm bảo tính toàn vẹn dữ liệu
            DB::beginTransaction();

            // Tính toán tổng giá trị đơn hàng
            $totalPrice = 0;
            $orderItems = [];

            foreach ($request->products as $product) {
                $productInfo = Product::findOrFail($product['product_id']);

                // Tính giá sản phẩm theo size
                $productPrice = $productInfo->price;
                if ($product['size'] === 'M') {
                    $productPrice += 6000;
                } else if ($product['size'] === 'L') {
                    $productPrice += 10000;
                }

                // Tính giá topping nếu có
                $toppingPrice = 0;
                if (! empty($product['topping_items'])) {
                    foreach ($product['topping_items'] as $topping) {
                        $toppingInfo = Topping::findOrFail($topping['id']);
                        $toppingPrice += $toppingInfo->price;
                    }
                }

                // Tính tổng giá cho sản phẩm này
                $itemTotal = ($productPrice + $toppingPrice) * $product['product_quantity'];
                $totalPrice += $itemTotal;

                // Lưu thông tin sản phẩm để tạo order items sau
                $orderItems[] = [
                    'product_id'       => $product['product_id'],
                    'product_name'     => $productInfo->name,
                    'product_price'    => $productPrice,
                    'product_quantity' => $product['product_quantity'],
                    'topping_items'    => $product['topping_items'] ?? [],
                    'size'             => $product['size'],
                    'item_note'        => $product['item_note'] ?? '',
                ];
            }

            // Xử lý voucher nếu có
            $discountAmount = 0;
            $voucher        = null;
            if ($request->filled('voucher_id')) {
                $voucher = Voucher::where('id', $request->voucher_id)
                    ->where('active', true)
                    ->where('expire_at', '>', now())
                    ->whereRaw('total_quantity > used_quantity')
                    ->first();

                if (! $voucher) {
                    \Log::error('Mã voucher không hợp lệ hoặc đã hết hạn');
                    return response()->json([
                        'status'  => false,
                        'message' => 'Mã voucher không hợp lệ hoặc đã hết hạn',
                    ], 400);
                }

                // Kiểm tra số lần sử dụng của user
                $usageCount = VoucherUsage::where('voucher_id', $voucher->id)
                    ->where('user_id', $user->id) // Sửa lại user_id
                    ->count();

                if ($usageCount >= $voucher->limit_per_user) {
                    \Log::error('Bạn đã sử dụng hết số lần cho phép của voucher này');
                    return response()->json([
                        'status'  => false,
                        'message' => 'Bạn đã sử dụng hết số lần cho phép của voucher này',
                    ], 400);
                }

                // Kiểm tra giá trị đơn hàng tối thiểu
                if ($totalPrice < $voucher->min_order_amount) {
                    \Log::error('Giá trị đơn hàng chưa đạt yêu cầu để sử dụng voucher');
                    return response()->json([
                        'status'  => false,
                        'message' => 'Giá trị đơn hàng chưa đạt yêu cầu để sử dụng voucher',
                    ], 400);
                }

                // Tính toán số tiền giảm giá
                if ($voucher->discount_type === 'percent') {
                    $discountAmount = min(
                        $totalPrice * ($voucher->discount_percent / 100),
                        $voucher->max_discount_amount
                    );
                } else {
                    $discountAmount = min(
                        $voucher->max_discount_amount,
                        $totalPrice
                    );
                }
            }

            // Tạo mã đơn hàng duy nhất
            $orderCode = $this->generateOrderCode($user->id);

            // Tính toán giá cuối cùng
            $shippingFee = $request->shipping_fee ?? 0;
            $finalPrice  = $totalPrice - $discountAmount + $shippingFee;

            // Tạo đơn hàng
            $order = Order::create([
                'user_id'         => $user->id,
                'user_name'       => $request->user_name,
                'mobile_no'       => $request->mobile_no,
                'address'         => $request->address,
                'note'            => $request->note ?? '',
                'shipping_fee'    => $shippingFee,
                'total_price'     => $totalPrice,
                'discount_amount' => $discountAmount,
                'final_price'     => $finalPrice,
                'payment_method'  => $request->payment_method,
                'payment_status'  => '0',
                'status'          => '0',
                'order_code'      => $orderCode,
            ]);

            // Nếu có sử dụng voucher
            if ($voucher) {
                // Tăng số lượng đã sử dụng của voucher
                $voucher->increment('used_quantity');

                // Tạo bản ghi sử dụng voucher
                VoucherUsage::create([
                    'voucher_id' => $voucher->id,
                    'user_id'    => $user->id,
                    'order_id'   => $order->id,
                ]);
            }

            // Thêm các sản phẩm vào đơn hàng
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id'         => $order->id,
                    'product_id'       => $item['product_id'],
                    'product_name'     => $item['product_name'],
                    'product_price'    => $item['product_price'],
                    'product_quantity' => $item['product_quantity'],
                    'topping_items'    => json_encode($item['topping_items']),
                    'size'             => $item['size'],
                    'item_note'        => $item['item_note'],
                ]);
            }

            // Nếu mọi thứ OK, commit transaction
            DB::commit();

            // Gửi event realtime thông báo đơn hàng mới
            event(new NewOrderEvent($order));

            return response()->json([
                'status'  => true,
                'message' => 'Đặt hàng thành công',
                'data'    => [
                    'order_code' => $order->order_code,
                ],
            ], 200);
        } catch (\Exception $err) {
            // Nếu có lỗi, rollback tất cả thay đổi
            \Log::error('Lỗi khi đặt hàng: ' . $err->getMessage());
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Đặt hàng thất bại',
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
            $order->payment_status = '22'; // Chỉ cập nhật trạng thái thanh toán
            $order->save();
            return response()->json(['message' => 'Cập nhật trạng thái thanh toán thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cập nhật trạng thái thanh toán thất bại', 'error' => $e->getMessage()], 400);
        }
    }

    //xác nhận đơn hàng đểđể giao
    public function startDelivery($id)
    {
        $order = Order::where('id', $id)->first();
        if (! $order) {
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy đơn hàng',
            ], 404);
        }
        // Kiểm tra xem đơn hàng đã thanh toán chưa
        if ($order->payment_status === '0' && $order->payment_method !== 'cod') {
            \Log::error('Lỗi khi cập nhật trạng thái đơn hàng #' . $id . ': Đơn hàng chưa thanh toán');
            return response()->json([
                'status'  => false,
                'message' => 'Không thể giao hàng vì đơn hàng chưa thanh toán',
            ], 400);
        }
        try {
            $order->status = '1'; // Đang giao hàng
            $order->save();
            return response()->json([
                'status'  => true,
                'message' => 'Cập nhật trạng thái đơn hàng thành công',
                'data'    => [
                    'order_code' => $order->order_code,
                ],
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Lỗi khi cập nhật trạng thái đơn hàng #' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Cập nhật trạng thái đơn hàng thất bại',
            ], 400);
        }
    }

    /**
     * Xác nhận giao hàng/nhận hàng thành công
     */
    public function successOrder(Request $request, $order_id)
    {
        // Lấy thông tin xác thực từ request đã được merge bởi middleware
        $user    = $request->user;
        $admin   = $request->admin;
        $isAdmin = ! is_null($admin);

        $order = Order::find($order_id);
        if (! $order) {
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy đơn hàng',
            ], 404);
        }

        // Nếu là user thông thường, kiểm tra quyền sở hữu
        if (! $isAdmin) {
            if ($order->user_id !== $user->id) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Bạn không có quyền hủy đơn hàng này',
                ], 403);
            }

            // Kiểm tra trạng thái đơn hàng
            if ($order->status !== '1') {
                \Log::error('Lỗi khi cập nhật đơn hàng #' . $order_id . ': Đơn hàng không ở trạng thái "đang giao"');
                return response()->json([
                    'status'  => false,
                    'message' => 'Chỉ có thể xác nhận giao hàng khi đơn hàng đang ở trạng thái "đang giao"',
                ], 400);
            }
        }
        try {
            DB::beginTransaction();

            $order->status = '2'; // Giao hàng thành công
            $order->save();

            DB::commit();
            return response()->json([
                'status'  => true,
                'message' => $isAdmin ? 'Xác nhận giao hàng thành công' : 'Xác nhận nhận hàng thành công',
                'data'    => [
                    'order_code' => $order->order_code,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Lỗi khi xác nhận giao hàng đơn #' . $order_id . ': ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => $isAdmin ? 'Xác nhận giao hàng thất bại' : 'Xác nhận nhận hàng thất bại',
            ], 400);
        }
    }

    /**
     * Hủy đơn hàng
     */
    public function cancelOrder(Request $request, $order_id)
    {
        // Lấy thông tin xác thực từ request đã được merge bởi middleware
        $user    = $request->user;
        $admin   = $request->admin;
        $isAdmin = ! is_null($admin);

        $order = Order::find($order_id);
        if (! $order) {
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy đơn hàng',
            ], 404);
        }

        // Nếu là user thông thường, kiểm tra quyền sở hữu và các điều kiện hủy đơn
        if (! $isAdmin) {
            if ($order->user_id !== $user->id) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Bạn không có quyền hủy đơn hàng này',
                ], 403);
            }

            // Kiểm tra trạng thái đơn hàng (chỉ áp dụng cho user)
            if ($order->status !== '0') {
                \Log::error('Lỗi khi hủy đơn hàng #' . $order_id . ': Đơn hàng không ở trạng thái "chờ xử lý"');
                return response()->json([
                    'status'  => false,
                    'message' => 'Chỉ có thể hủy đơn hàng khi đang ở trạng thái "chờ xử lý"',
                ], 400);
            }

            // Kiểm tra phương thức thanh toán (chỉ áp dụng cho user)
            if ($order->payment_method !== 'cod' && $order->payment_status === '1') {
                \Log::error('Lỗi khi hủy đơn hàng #' . $order_id . ': Đơn hàng đã thanh toán');
                return response()->json([
                    'status'  => false,
                    'message' => 'Không thể hủy đơn hàng đã thanh toán',
                ], 400);
            }
        }

        try {
            DB::beginTransaction();

            $order->status = '-1'; // Hủy đơn hàng
            $order->save();

            // Nếu có sử dụng voucher, hoàn trả số lượng đã sử dụng
            if ($order->voucher_id) {
                $voucher = Voucher::find($order->voucher_id);
                if ($voucher) {
                    $voucher->decrement('used_quantity');
                    // Xóa bản ghi sử dụng voucher
                    VoucherUsage::where('order_id', $order->id)->delete();
                }
            }

            DB::commit();
            return response()->json([
                'status'  => true,
                'message' => 'Hủy đơn hàng thành công',
                'data'    => [
                    'order_code' => $order->order_code,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Lỗi khi hủy đơn hàng #' . $order_id . ': ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Hủy đơn hàng thất bại',
            ], 400);
        }
    }

    //xem cac don hang cua user
    public function getOrderHistory()
    {
        $user   = auth('user')->user();
        $orders = Order::where('user_id', $user->id)
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
        ? response()->json([
            'status'  => false,
            'message' => 'Không có lịch sử đơn hàng',
        ], 404)
        : response()->json([
            'status'  => true,
            'message' => 'Lấy lịch sử đơn hàng thành công',
            'data'    => [
                'orders' => $orders,
            ],
        ], 200);
    }

    //lay thong tin don hang theo id
    public function getOrderInfo($id)
    {
        $order = Order::where('id', $id)->with('orderItems')->first();
        if (! $order) {
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy đơn hàng',
            ], 404);
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

        return response()->json([
            'status'  => true,
            'message' => 'Lấy thông tin đơn hàng thành công',
            'data'    => [
                'order' => $order,
            ],
        ], 200);
    }

    //lay don hang hoan thanh
    public function getSuccessOrders()
    {
        $orders = Order::where('status', '2')
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
        ? response()->json([
            'status'  => false,
            'message' => 'Không tìm thấy đơn hàng',
        ], 404)
        : response()->json([
            'status'  => true,
            'message' => 'Lấy thông tin đơn hàng thành công',
            'data'    => [
                'orders' => $orders,
            ],
        ], 200);
    }

    // Lấy đơn hàng chờ thanh toán online
    public function getPendingPaymentOrders()
    {
        $orders = Order::where('status', '0')
            ->where('payment_status', '0')
            ->where('payment_method', '!=', 'cod')
            ->orderby('id', 'desc')
            ->with('orderItems')
            ->get();

        foreach ($orders as $order) {
            foreach ($order->orderItems as $item) {
                $item->product_price = (float) $item->product_price;
                $item->topping_items = json_decode($item->topping_items, true);
            }
            $order->total_price     = (float) $order->total_price;
            $order->discount_amount = (float) $order->discount_amount;
            $order->shipping_fee    = (float) $order->shipping_fee;
            $order->final_price     = (float) $order->final_price;
        }

        return $orders->isEmpty()
        ? response()->json([
            'status'  => false,
            'message' => 'Không có đơn hàng chờ thanh toán',
        ], 404)
        : response()->json([
            'status'  => true,
            'message' => 'Lấy danh sách đơn hàng chờ thanh toán thành công',
            'data'    => [
                'orders' => $orders,
            ],
        ], 200);
    }

    // Lấy đơn hàng chờ giao (COD và đã thanh toán online)
    public function getPendingDeliveryOrders()
    {
        $orders = Order::where('status', '0')
            ->where(function ($query) {
                $query->where('payment_method', 'cod')
                    ->orWhere('payment_status', '1');
            })
            ->orderby('id', 'desc')
            ->with('orderItems')
            ->get();

        foreach ($orders as $order) {
            foreach ($order->orderItems as $item) {
                $item->product_price = (float) $item->product_price;
                $item->topping_items = json_decode($item->topping_items, true);
            }
            $order->total_price     = (float) $order->total_price;
            $order->discount_amount = (float) $order->discount_amount;
            $order->shipping_fee    = (float) $order->shipping_fee;
            $order->final_price     = (float) $order->final_price;
        }

        return $orders->isEmpty()
        ? response()->json([
            'status'  => false,
            'message' => 'Không có đơn hàng chờ giao',
        ], 404)
        : response()->json([
            'status'  => true,
            'message' => 'Lấy danh sách đơn hàng chờ giao thành công',
            'data'    => [
                'orders' => $orders,
            ],
        ], 200);
    }

    // Lấy đơn hàng đang giao
    public function getDeliveringOrders()
    {
        $orders = Order::where('status', '1')
            ->orderby('id', 'desc')
            ->with('orderItems')
            ->get();

        foreach ($orders as $order) {
            foreach ($order->orderItems as $item) {
                $item->product_price = (float) $item->product_price;
                $item->topping_items = json_decode($item->topping_items, true);
            }
            $order->total_price     = (float) $order->total_price;
            $order->discount_amount = (float) $order->discount_amount;
            $order->shipping_fee    = (float) $order->shipping_fee;
            $order->final_price     = (float) $order->final_price;
        }

        return $orders->isEmpty()
        ? response()->json([
            'status'  => false,
            'message' => 'Không có đơn hàng đang giao',
        ], 404)
        : response()->json([
            'status'  => true,
            'message' => 'Lấy danh sách đơn hàng đang giao thành công',
            'data'    => [
                'orders' => $orders,
            ],
        ], 200);
    }
}
