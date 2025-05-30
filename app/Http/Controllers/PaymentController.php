<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    //thanh toán qua momo
    public function execPostRequest($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)]
        );
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        //execute post
        $result = curl_exec($ch);
        //close connection
        curl_close($ch);
        return $result;
    }

    /**
     * Tạo giao dịch thanh toán mới
     */
    private function createPayment($order, $amount, $paymentMethod, $status = 'pending', $transactionId = null, $gatewayResponse = null)
    {
        return Payment::create([
            'order_id'                 => $order->id,
            'order_code'               => $order->order_code,
            'amount'                   => $amount,
            'payment_method'           => $paymentMethod,
            'status'                   => '0',
            'transaction_id'           => $transactionId,
            'payment_gateway_response' => $gatewayResponse ? json_encode($gatewayResponse) : null,
        ]);
    }

    /**
     * Cập nhật trạng thái thanh toán của đơn hàng
     */
    private function updateOrderPaymentStatus($order)
    {
        $totalPaid = $order->getTotalPaidAmount();

        if ($totalPaid >= $order->final_price) {
            $order->payment_status = '1'; // Đã thanh toán đủ
        } else {
            $order->payment_status = '0'; // Chưa thanh toán
        }

        $order->save();

        return $order;
    }

    //=============================================== MOMO ================================================
    public function momo_payment(Request $request)
    {
        // Xác thực và tìm đơn hàng
        $order = Order::where('order_code', $request->order_code)->first();
        if (! $order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Kiểm tra nếu đơn hàng đã thanh toán
        if ($order->payment_status == '1') {
            return response()->json(['message' => 'Đơn hàng này đã được thanh toán'], 400);
        }

        // Cấu hình MOMO
        $config = [
            'endpoint'    => "https://test-payment.momo.vn/v2/gateway/api/create",
            'partnerCode' => 'MOMOBKUN20180529',
            'accessKey'   => 'klm05TvNBzhg7h7j',
            'secretKey'   => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
            'storeId'     => "MomoTestStore",
        ];

        // Tạo mã giao dịch duy nhất
        $uniqueTransactionId = $order->order_code . '_' . time();

        // Thông tin thanh toán
        $requestId   = time() . "";
        $requestType = "captureWallet";
        $returnUrl   = $request->return_url;
        $ipnUrl      = env('APP_URL') . '/api/v1/payment/momo/callback';
        $amount      = (int) $order->final_price;

        // Tạo payment record trước khi gửi request
        $payment                 = $this->createPayment($order, $amount, 'momo');
        $payment->transaction_id = $uniqueTransactionId;
        $payment->save();

        // Tạo payload cho request
        $payload = [
            'partnerCode' => $config['partnerCode'],
            'partnerName' => "Test",
            'storeId'     => $config['storeId'],
            'requestId'   => $requestId,
            'amount'      => $amount,
            'orderId'     => $uniqueTransactionId, // Sử dụng mã duy nhất
            'orderInfo'   => "Thanh toán đơn hàng " . $order->order_code,
            'redirectUrl' => $returnUrl,
            'ipnUrl'      => $ipnUrl,
            'lang'        => 'vi',
            'extraData'   => $payment->id,
            'requestType' => $requestType,
        ];

        // Tạo chữ ký
        $rawHash = "accessKey={$config['accessKey']}&amount={$payload['amount']}&extraData={$payload['extraData']}"
            . "&ipnUrl={$payload['ipnUrl']}&orderId={$payload['orderId']}&orderInfo={$payload['orderInfo']}"
            . "&partnerCode={$payload['partnerCode']}&redirectUrl={$payload['redirectUrl']}"
            . "&requestId={$payload['requestId']}&requestType={$payload['requestType']}";

        $payload['signature'] = hash_hmac("sha256", $rawHash, $config['secretKey']);

        try {
            // Gọi API MOMO
            $result     = $this->execPostRequest($config['endpoint'], json_encode($payload));
            $jsonResult = json_decode($result, true);

            // Kiểm tra kết quả trả về
            if (! isset($jsonResult['payUrl'])) {
                \Log::error('MOMO payment error', ['response' => $jsonResult]);

                // Cập nhật payment record
                $payment->update([
                    'status'                   => 'failed',
                    'payment_gateway_response' => json_encode($jsonResult),
                ]);

                return response()->json([
                    'message' => 'Không nhận được URL thanh toán',
                    'details' => $jsonResult,
                ], 400);
            }

            // Cập nhật payment record với thông tin giao dịch
            $payment->update([
                'transaction_id'           => $jsonResult['orderId'],
                'payment_gateway_response' => json_encode($jsonResult),
            ]);

            return response()->json(['payUrl' => $jsonResult['payUrl']], 200);
        } catch (\Exception $e) {
            \Log::error('MOMO payment exception', ['message' => $e->getMessage()]);

            // Cập nhật payment record khi gặp lỗi
            $payment->update([
                'status' => '2',
            ]);

            return response()->json([
                'message' => 'Gửi yêu cầu thanh toán thất bại',
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Xử lý callback từ Momo
     */
    public function momo_callback(Request $request)
    {
        // Kiểm tra request
        $resultCode = $request->resultCode;
        $orderId    = $request->orderId;

        // Tìm đơn hàng và payment
        $order = Order::where('order_code', $orderId)->first();
        if (! $order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        $paymentId = $request->extraData;
        $payment   = Payment::find($paymentId);

        if (! $payment) {
            // Nếu không tìm thấy payment, có thể tạo một payment mới
            $payment = $this->createPayment(
                $order,
                $request->amount,
                'momo',
                ($resultCode == '0') ? 'completed' : 'failed',
                $request->transId,
                $request->all()
            );
        } else {
            // Cập nhật payment hiện tại
            $payment->update([
                'status'                   => ($resultCode == '0') ? 'completed' : 'failed',
                'transaction_id'           => $request->transId,
                'payment_gateway_response' => json_encode($request->all()),
            ]);
        }

        // Cập nhật trạng thái thanh toán của đơn hàng
        if ($resultCode == '0') {
            $this->updateOrderPaymentStatus($order);
        }

        return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=' . (($resultCode == '0') ? 'success' : 'failure') . '&order_code=' . $orderId);
    }

    //=============================================== VNPAY ================================================
    /**
     * Tạo URL thanh toán VNPay
     */
    public function vnpay_payment(Request $request)
    {
        // Xác thực và tìm đơn hàng
        $order = Order::where('order_code', $request->order_code)->first();
        if (! $order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Kiểm tra nếu đơn hàng đã thanh toán
        if ($order->payment_status == '1') {
            return response()->json(['message' => 'Đơn hàng này đã được thanh toán'], 400);
        }

        // Cấu hình VNPay
        $vnp_Url        = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_Returnurl  = $request->return_url;
        $vnp_TmnCode    = env('VNPAY_TMN_CODE', '6L2C6CQ8');                            // Mã website tại VNPAY
        $vnp_HashSecret = env('VNPAY_HASH_SECRET', 'PAET5F7FLORDW0TRDKASYARL4M17DUGC'); // Chuỗi bí mật

        // Tạo mã giao dịch duy nhất
        $vnp_TxnRef     = $order->order_code . '_' . time();
        $vnp_OrderInfo  = "Thanh toan don hang " . $order->order_code;
        $vnp_OrderType  = 'billpayment';
        $vnp_Amount     = $order->final_price * 100; // Số tiền * 100
        $vnp_Locale     = 'vn';
        $vnp_IpAddr     = $request->ip(); // IP của người dùng
        $vnp_CreateDate = date('YmdHis');

        // Tạo payment record
        $payment                 = $this->createPayment($order, $order->final_price, 'vnpay');
        $payment->transaction_id = $vnp_TxnRef;
        $payment->save();

        $inputData = [
            "vnp_Version"    => "2.1.0",
            "vnp_TmnCode"    => $vnp_TmnCode,
            "vnp_Amount"     => $vnp_Amount,
            "vnp_Command"    => "pay",
            "vnp_CreateDate" => $vnp_CreateDate,
            "vnp_CurrCode"   => "VND",
            "vnp_IpAddr"     => $vnp_IpAddr,
            "vnp_Locale"     => $vnp_Locale,
            "vnp_OrderInfo"  => $vnp_OrderInfo,
            "vnp_OrderType"  => $vnp_OrderType,
            "vnp_ReturnUrl"  => $vnp_Returnurl,
            "vnp_TxnRef"     => $vnp_TxnRef,
        ];

        ksort($inputData);
        $query    = "";
        $i        = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }

        // Cập nhật payment record với thông tin giao dịch
        $payment->update([
            'payment_gateway_response' => json_encode($inputData),
        ]);

        return response()->json(['payUrl' => $vnp_Url], 200);
    }

    /**
     * Xử lý callback từ VNPay
     */
    public function vnpay_callback(Request $request)
    {
        $vnp_HashSecret = env('VNPAY_HASH_SECRET', 'MKESFZQOZQZQZQZQZQZQZQZQZQZQZQZQ');
        $inputData      = [];
        $data           = $request->all();

        foreach ($data as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }

        $vnp_SecureHash = $inputData['vnp_SecureHash'];
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        $i        = 0;
        $hashData = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        // Tìm đơn hàng từ mã giao dịch
        $orderCode = explode('_', $inputData['vnp_TxnRef'])[0];
        $order     = Order::where('order_code', $orderCode)->first();

        if (! $order) {
            return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=failure&message=Không tìm thấy đơn hàng');
        }

        // Tìm payment record
        $payment = Payment::where('transaction_id', $inputData['vnp_TxnRef'])->first();

        if ($secureHash == $vnp_SecureHash) {
            if ($inputData['vnp_ResponseCode'] == '00') {
                // Thanh toán thành công
                if ($payment) {
                    $payment->update([
                        'status'                   => 'completed',
                        'payment_gateway_response' => json_encode($inputData),
                    ]);
                }

                // Cập nhật trạng thái đơn hàng
                $this->updateOrderPaymentStatus($order);

                return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=success&order_code=' . $orderCode);
            } else {
                // Thanh toán thất bại
                if ($payment) {
                    $payment->update([
                        'status'                   => 'failed',
                        'payment_gateway_response' => json_encode($inputData),
                    ]);
                }

                return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=failure&order_code=' . $orderCode);
            }
        } else {
            // Chữ ký không hợp lệ
            if ($payment) {
                $payment->update([
                    'status'                   => 'failed',
                    'payment_gateway_response' => json_encode($inputData),
                ]);
            }

            return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=failure&message=Chữ ký không hợp lệ');
        }
    }

    //=============================================== ZALOPAY ================================================
    /**
     * Tạo đơn hàng thanh toán ZaloPay
     */
    public function zalopay_payment(Request $request)
    {
        $order = Order::where('order_code', $request->order_code)->first();
        if (! $order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        if ($order->payment_status == '1') {
            return response()->json(['message' => 'Đơn hàng này đã được thanh toán'], 400);
        }

        $config = [
            'app_id'   => env('ZALOPAY_APP_ID', '2553'),
            'key1'     => env('ZALOPAY_KEY1', 'PcY4iZIKFCIdgZvA6ueMcMHHUbRLYjPL'),
            'key2'     => env('ZALOPAY_KEY2', 'kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz'),
            'endpoint' => 'https://sb-openapi.zalopay.vn/v2/create',
        ];

        $now          = Carbon::now('Asia/Ho_Chi_Minh');
        $app_trans_id = $now->format('ymd') . '_' . $order->order_code . '_' . round(microtime(true) * 1000);

        $payment                 = $this->createPayment($order, $order->final_price, 'zalopay');
        $payment->transaction_id = $app_trans_id;
        $payment->save();

        $embed_data = json_encode([
            'merchantinfo' => 'embeddata123',
            'redirecturl'  => $request->return_url,
        ]);

        $items = json_encode([[
            'name'     => 'Thanh toan don hang ' . $order->order_code,
            'price'    => (int) $order->final_price,
            'quantity' => 1,
        ]]);

        $order_data = [
            'app_id'       => $config['app_id'],
            'app_trans_id' => $app_trans_id,
            'app_user'     => 'user_123',
            'app_time'     => round(microtime(true) * 1000),
            'amount'       => (int) $order->final_price,
            'item'         => $items,
            'description'  => 'Thanh toan don hang ' . $order->order_code,
            'embed_data'   => $embed_data,
            'bank_code'    => 'zalopayapp',
            'callback_url' => env('APP_URL') . '/api/v1/payment/zalopay/callback',
        ];

        $data = $order_data['app_id'] . '|' .
            $order_data['app_trans_id'] . '|' .
            $order_data['app_user'] . '|' .
            $order_data['amount'] . '|' .
            $order_data['app_time'] . '|' .
            $order_data['embed_data'] . '|' .
            $order_data['item'];

        $order_data['mac'] = hash_hmac('sha256', $data, $config['key1']);

        try {
            $response = Http::asForm()
                ->post($config['endpoint'], $order_data);

            $jsonResult = $response->json();

            if ($jsonResult['return_code'] == 1) {
                $payment->update([
                    'payment_gateway_response' => json_encode($jsonResult),
                ]);

                return response()->json([
                    'order_url'   => $jsonResult['order_url'],
                    'order_token' => $jsonResult['order_token'],
                ], 200);
            }

            $payment->update([
                'status'                   => 'failed',
                'payment_gateway_response' => json_encode($jsonResult),
            ]);

            return response()->json([
                'message' => $jsonResult['return_message'] ?? 'Không thể tạo đơn hàng ZaloPay',
                'details' => $jsonResult,
            ], 400);

        } catch (\Exception $e) {
            $payment->update([
                'status'                   => 'failed',
                'payment_gateway_response' => json_encode(['error' => $e->getMessage()]),
            ]);

            return response()->json([
                'message' => 'Lỗi khi tạo đơn hàng ZaloPay',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Xử lý callback từ ZaloPay
     */
    public function zalopay_callback(Request $request)
    {
        $key2 = env('ZALOPAY_KEY2', 'kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz');

        try {
            $data = $request->all();
            $mac  = $data['mac'];
            unset($data['mac']);

            // Tạo chuỗi hash để verify
            $dataStr = '';
            foreach ($data as $key => $value) {
                $dataStr .= $key . '=' . $value . '&';
            }
            $dataStr   = rtrim($dataStr, '&');
            $macVerify = hash_hmac('sha256', $dataStr, $key2);

            if ($mac !== $macVerify) {
                return response()->json(['return_code' => -1, 'return_message' => 'Chữ ký không hợp lệ']);
            }

                                                                 // Tìm đơn hàng và payment
            $orderCode = explode('_', $data['app_trans_id'])[1]; // Lấy phần order_code từ app_trans_id
            $order     = Order::where('order_code', $orderCode)->first();
            if (! $order) {
                return response()->json(['return_code' => -1, 'return_message' => 'Không tìm thấy đơn hàng']);
            }

            $payment = Payment::where('transaction_id', $data['app_trans_id'])->first();
            if (! $payment) {
                return response()->json(['return_code' => -1, 'return_message' => 'Không tìm thấy giao dịch thanh toán']);
            }

            if ($data['status'] == 1) {
                $payment->update([
                    'status'                   => 'completed',
                    'payment_gateway_response' => json_encode($data),
                ]);
                $this->updateOrderPaymentStatus($order);
                return response()->json(['return_code' => 1, 'return_message' => 'success']);
            }

            $payment->update([
                'status'                   => 'failed',
                'payment_gateway_response' => json_encode($data),
            ]);
            return response()->json(['return_code' => -1, 'return_message' => 'Thanh toán thất bại']);

        } catch (\Exception $e) {
            return response()->json([
                'return_code'    => -1,
                'return_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Kiểm tra trạng thái đơn hàng ZaloPay
     */
    public function zalopay_check_status(Request $request)
    {
        $app_id   = env('ZALOPAY_APP_ID', '2553');
        $key1     = env('ZALOPAY_KEY1', 'PcY4iZIKFCIdgZvA6ueMcMHHUbRLYjPL');
        $endpoint = "https://sandbox.zalopay.com.vn/v001/tpe/getstatusbyapptransid";

        $data = [
            'app_id'       => $app_id,
            'app_trans_id' => $request->app_trans_id,
            'mac'          => hash_hmac('sha256', $app_id . '|' . $request->app_trans_id, $key1),
        ];

        try {
            $result = $this->execPostRequest($endpoint, json_encode($data));
            return response()->json(json_decode($result, true));
        } catch (\Exception $e) {
            return response()->json([
                'return_code'    => -1,
                'return_message' => $e->getMessage(),
            ], 500);
        }
    }

    //=============================================== COD ================================================
    /**
     * Xử lý thanh toán khi giao hàng (COD)
     */
    public function cod_payment(Request $request)
    {
        // Tìm đơn hàng
        $order = Order::where('order_code', $request->order_code)->first();
        if (! $order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Kiểm tra phương thức thanh toán của đơn hàng
        if ($order->payment_method != 'cod') {
            return response()->json(['message' => 'Phương thức thanh toán không phù hợp'], 400);
        }

        // Tạo giao dịch thanh toán mới (chưa thanh toán, chỉ đánh dấu là COD)
        $payment = $this->createPayment(
            $order,
            $order->final_price,
            'cod',
            'pending'
        );

        return response()->json([
            'message'    => 'Đã đánh dấu đơn hàng thanh toán khi nhận hàng',
            'order_code' => $order->order_code,
        ], 200);
    }

    /**
     * Cập nhật thanh toán COD khi đã giao hàng thành công
     */
    public function complete_cod_payment(Request $request)
    {
        // Tìm đơn hàng
        $order = Order::where('order_code', $request->order_code)->first();
        if (! $order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        // Kiểm tra phương thức thanh toán
        if ($order->payment_method != 'cod') {
            return response()->json(['message' => 'Phương thức thanh toán không phải COD'], 400);
        }

        // Tìm payment chưa thanh toán
        $payment = $order->payments()->where('status', 'pending')->first();

        if ($payment) {
            // Cập nhật payment
            $payment->update([
                'status'       => 'completed',
                'payment_time' => Carbon::now(),
                'notes'        => 'Thanh toán COD khi giao hàng',
            ]);
        } else {
            // Tạo payment mới
            $payment = $this->createPayment(
                $order,
                $order->final_price,
                'cod',
                'completed',
                null,
                null
            );
        }

                                      // Cập nhật trạng thái thanh toán và trạng thái đơn hàng
        $order->payment_status = '1'; // Đã thanh toán
        $order->status         = '2'; // Đã hoàn thành
        $order->save();

        return response()->json([
            'message'    => 'Đã cập nhật thanh toán COD thành công',
            'order_code' => $order->order_code,
        ], 200);
    }

}
