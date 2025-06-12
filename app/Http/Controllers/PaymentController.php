<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

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
    private function createPayment($order, $amount, $paymentMethod, $status = '0', $transactionId = null, $gatewayResponse = null)
    {
        return Payment::create([
            'order_id'                 => $order->id,
            'order_code'               => $order->order_code,
            'amount'                   => $amount,
            'payment_method'           => $paymentMethod,
            'status'                   => $status,
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
        $user = auth('user')->user();

        // Validate input
        $validator = Validator::make($request->all(), [
            'order_code' => 'required|string|exists:orders,order_code',
            'return_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $order = Order::where('order_code', $request->order_code)->first();

            // Kiểm tra quyền sở hữu đơn hàng
            if ($order->user_id != $user->id) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Bạn không có quyền thanh toán đơn hàng này',
                ], 403);
            }

            // Kiểm tra trạng thái đơn hàng
            if ($order->payment_status == '1') {
                \Log::error('Đơn hàng này đã được thanh toán #' . $order->id);
                return response()->json([
                    'status'  => false,
                    'message' => 'Đơn hàng này đã được thanh toán',
                ], 400);
            }

            // Cấu hình MOMO
            $config = config('services.momo');
            \Log::info('MOMO config', $config);
            // Tạo mã giao dịch duy nhất
            $uniqueTransactionId = $order->order_code . '_' . time();

            // Tạo payment record
            $payment                 = $this->createPayment($order, $order->final_price, 'momo');
            $payment->transaction_id = $uniqueTransactionId;
            $payment->save();

            // Tạo payload cho request
            $payload = [
                'partnerCode' => config('services.momo.partner_code'),
                'partnerName' => env('APP_NAME', 'Test'),
                'storeId'     => config('services.momo.store_id'),
                'requestId'   => time() . "",
                'amount'      => (int) $order->final_price,
                'orderId'     => $uniqueTransactionId,
                'orderInfo'   => "Thanh toán đơn hàng " . $order->order_code,
                'redirectUrl' => $request->return_url,
                'ipnUrl'      => env('APP_URL') . '/api/v1/payments/momo/callback',
                'lang'        => 'vi',
                'extraData'   => $payment->id,
                'requestType' => "captureWallet",
            ];

            // Tạo chữ ký
            $rawHash = "accessKey={config('services.momo.access_key')}&amount={$payload['amount']}&extraData={$payload['extraData']}"
                . "&ipnUrl={$payload['ipnUrl']}&orderId={$payload['orderId']}&orderInfo={$payload['orderInfo']}"
                . "&partnerCode={$payload['partnerCode']}&redirectUrl={$payload['redirectUrl']}"
                . "&requestId={$payload['requestId']}&requestType={$payload['requestType']}";

            $payload['signature'] = hash_hmac("sha256", $rawHash, config('services.momo.secret_key'));

            // Gọi API MOMO
            $result     = $this->execPostRequest(config('services.momo.endpoint'), json_encode($payload));
            $jsonResult = json_decode($result, true);

            if (! isset($jsonResult['payUrl'])) {
                \Log::error('MOMO payment error', [
                    'order_code' => $order->order_code,
                    'response'   => $jsonResult,
                ]);

                $payment->update([
                    'status'                   => 'failed',
                    'payment_gateway_response' => json_encode($jsonResult),
                ]);

                DB::commit();
                return response()->json([
                    'status'  => false,
                    'message' => 'Không thể tạo yêu cầu thanh toán',
                    'details' => $jsonResult,
                ], 400);
            }

            // Cập nhật payment record
            $payment->update([
                'transaction_id'           => $jsonResult['orderId'],
                'payment_gateway_response' => json_encode($jsonResult),
            ]);

            DB::commit();
            return response()->json([
                'status'  => true,
                'message' => 'Gửi yêu cầu thanh toán thành công',
                'data'    => [
                    'payUrl' => $jsonResult['payUrl'],
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('MOMO payment exception', [
                'order_code' => $request->order_code,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Có lỗi xảy ra khi xử lý thanh toán',
            ], 500);
        }
    }

    /**
     * Xử lý callback từ Momo
     */
    public function momo_callback(Request $request)
    {
        try {
            DB::beginTransaction();

            $resultCode = $request->resultCode;
            $orderId    = $request->orderId;
            $paymentId  = $request->extraData;

            // Tìm payment record
            $payment = Payment::find($paymentId);
            if (! $payment) {
                \Log::error('MOMO callback: Payment not found', ['payment_id' => $paymentId]);
                return response()->json([
                    'status'  => false,
                    'message' => 'Không tìm thấy giao dịch thanh toán',
                ], 404);
            }

            $order = $payment->order;
            if (! $order) {
                \Log::error('MOMO callback: Order not found', ['payment_id' => $paymentId]);
                return response()->json([
                    'status'  => false,
                    'message' => 'Không tìm thấy đơn hàng',
                ], 404);
            }

            // Cập nhật payment record
            $payment->update([
                'status'                   => ($resultCode == '0') ? 'completed' : 'failed',
                'transaction_id'           => $request->transId,
                'payment_gateway_response' => json_encode($request->all()),
            ]);

            // Cập nhật trạng thái đơn hàng nếu thanh toán thành công
            if ($resultCode == '0') {
                $this->updateOrderPaymentStatus($order);
            }

            DB::commit();
            return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=' .
                (($resultCode == '0') ? 'success' : 'failure') . '&order_code=' . $order->order_code);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('MOMO callback exception', [
                'request' => $request->all(),
                'error'   => $e->getMessage(),
            ]);

            return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=failure&message=Lỗi xử lý thanh toán');
        }
    }

    //=============================================== VNPAY ================================================
    public function vnpay_payment(Request $request)
    {
        $user = auth('user')->user();
        // Validate input
        $validator = Validator::make($request->all(), [
            'order_code' => 'required|string|exists:orders,order_code',
            'return_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $order = Order::where('order_code', $request->order_code)->first();

            // Kiểm tra quyền sở hữu đơn hàng
            if ($order->user_id != $user->id) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Bạn không có quyền thanh toán đơn hàng này',
                ], 403);
            }

            // Kiểm tra trạng thái đơn hàng
            if ($order->payment_status == '1') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Đơn hàng này đã được thanh toán',
                ], 400);
            }

            if ($order->status != '0') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Đơn hàng không ở trạng thái chờ thanh toán',
                ], 400);
            }

            // Cấu hình VNPay
            $vnp_Url        = config('services.vnpay.url');
            $vnp_TmnCode    = config('services.vnpay.tmn_code');
            $vnp_HashSecret = config('services.vnpay.hash_secret');

            // Tạo mã giao dịch duy nhất
            $vnp_TxnRef = $order->order_code . '_' . time();

            // Tạo payment record
            $payment                 = $this->createPayment($order, $order->final_price, 'vnpay');
            $payment->transaction_id = $vnp_TxnRef;
            $payment->save();

            $inputData = [
                "vnp_Version"    => "2.1.0",
                "vnp_TmnCode"    => $vnp_TmnCode,
                "vnp_Amount"     => (int) ($order->final_price * 100),
                "vnp_Command"    => "pay",
                "vnp_CreateDate" => date('YmdHis'),
                "vnp_CurrCode"   => "VND",
                "vnp_IpAddr"     => $request->ip(),
                "vnp_Locale"     => "vn",
                "vnp_OrderInfo"  => "Thanh toan don hang " . $order->order_code,
                "vnp_OrderType"  => "billpayment",
                "vnp_ReturnUrl"  => $request->return_url,
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

            // Cập nhật payment record
            $payment->update([
                'payment_gateway_response' => json_encode($inputData),
            ]);

            DB::commit();
            return response()->json([
                'status'  => true,
                'message' => 'Gửi yêu cầu thanh toán thành công',
                'data'    => [
                    'payUrl' => $vnp_Url,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('VNPAY payment exception', [
                'order_code' => $request->order_code,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Có lỗi xảy ra khi xử lý thanh toán',
            ], 500);
        }
    }

    /**
     * Xử lý callback từ VNPay
     */
    public function vnpay_callback(Request $request)
    {
        try {
            DB::beginTransaction();

            $vnp_HashSecret = config('services.vnpay.hash_secret');
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

            if ($secureHash != $vnp_SecureHash) {
                \Log::error('VNPAY callback: Invalid signature', ['data' => $inputData]);
                return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=failure&message=Chữ ký không hợp lệ');
            }

            // Tìm đơn hàng từ mã giao dịch
            $orderCode = explode('_', $inputData['vnp_TxnRef'])[0];
            $order     = Order::where('order_code', $orderCode)->first();

            if (! $order) {
                \Log::error('VNPAY callback: Order not found', ['order_code' => $orderCode]);
                return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=failure&message=Không tìm thấy đơn hàng');
            }

            // Tìm payment record
            $payment = Payment::where('transaction_id', $inputData['vnp_TxnRef'])->first();
            if (! $payment) {
                \Log::error('VNPAY callback: Payment not found', ['transaction_id' => $inputData['vnp_TxnRef']]);
                return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=failure&message=Không tìm thấy giao dịch thanh toán');
            }

            if ($inputData['vnp_ResponseCode'] == '00') {
                // Thanh toán thành công
                $payment->update([
                    'status'                   => 'completed',
                    'payment_gateway_response' => json_encode($inputData),
                ]);

                $this->updateOrderPaymentStatus($order);
                DB::commit();

                return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=success&order_code=' . $orderCode);
            }

            // Thanh toán thất bại
            $payment->update([
                'status'                   => 'failed',
                'payment_gateway_response' => json_encode($inputData),
            ]);

            DB::commit();
            return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=failure&order_code=' . $orderCode);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('VNPAY callback exception', [
                'request' => $request->all(),
                'error'   => $e->getMessage(),
            ]);

            return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=failure&message=Lỗi xử lý thanh toán');
        }
    }

    //=============================================== ZALOPAY ================================================
    public function zalopay_payment(Request $request)
    {
        $user = auth('user')->user();

        // Validate input
        $validator = Validator::make($request->all(), [
            'order_code' => 'required|string|exists:orders,order_code',
            'return_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $order = Order::where('order_code', $request->order_code)->first();

            // Kiểm tra quyền sở hữu đơn hàng
            if ($order->user_id != $user->id) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Bạn không có quyền thanh toán đơn hàng này',
                ], 403);
            }

            // Kiểm tra trạng thái đơn hàng
            if ($order->payment_status == '1') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Đơn hàng này đã được thanh toán',
                ], 400);
            }

            if ($order->status != '0') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Đơn hàng không ở trạng thái chờ thanh toán',
                ], 400);
            }

            // Cấu hình ZaloPay
            $config = config('services.zalopay');

            $now          = Carbon::now('Asia/Ho_Chi_Minh');
            $app_trans_id = $now->format('ymd') . '_' . $order->order_code . '_' . round(microtime(true) * 1000);

            // Tạo payment record
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
                'app_user'     => 'user_' . $user->id,
                'app_time'     => round(microtime(true) * 1000),
                'amount'       => (int) $order->final_price,
                'item'         => $items,
                'description'  => 'Thanh toan don hang ' . $order->order_code,
                'embed_data'   => $embed_data,
                'bank_code'    => 'zalopayapp',
                'callback_url' => env('APP_URL') . '/api/v1/payments/zalopay/callback',
            ];

            $data = $order_data['app_id'] . '|' .
                $order_data['app_trans_id'] . '|' .
                $order_data['app_user'] . '|' .
                $order_data['amount'] . '|' .
                $order_data['app_time'] . '|' .
                $order_data['embed_data'] . '|' .
                $order_data['item'];

            $order_data['mac'] = hash_hmac('sha256', $data, $config['key1']);

            // Gọi API ZaloPay
            $response = Http::asForm()
                ->post($config['endpoint'], $order_data);

            $jsonResult = $response->json();

            if ($jsonResult['return_code'] == 1) {
                $payment->update([
                    'payment_gateway_response' => json_encode($jsonResult),
                ]);

                DB::commit();
                return response()->json([
                    'status'  => true,
                    'message' => 'Gửi yêu cầu thanh toán thành công',
                    'data'    => [
                        'payUrl'      => $jsonResult['order_url'],
                        'order_token' => $jsonResult['order_token'],
                    ],
                ], 200);
            }

            $payment->update([
                'status'                   => '0',
                'payment_gateway_response' => json_encode($jsonResult),
            ]);

            DB::commit();
            \Log::error('ZaloPay payment failed', [
                'order_code' => $request->order_code,
                'error'      => $jsonResult['return_message'] ?? 'Không thể tạo đơn hàng ZaloPay',
                'details'    => $jsonResult,
            ]);
            return response()->json([
                'status'  => false,
                'message' => $jsonResult['return_message'] ?? 'Không thể tạo đơn hàng ZaloPay',
                'details' => $jsonResult,
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ZaloPay payment exception', [
                'order_code' => $request->order_code,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Có lỗi xảy ra khi xử lý thanh toán',
            ], 500);
        }
    }

    /**
     * Xử lý callback từ ZaloPay
     */
    public function zalopay_callback(Request $request)
    {
        try {
            DB::beginTransaction();

            $key2 = config('services.zalopay.key2');
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
                \Log::error('ZaloPay callback: Invalid signature', ['data' => $data]);
                return response()->json([
                    'return_code'    => -1,
                    'return_message' => 'Chữ ký không hợp lệ',
                ]);
            }

            // Tìm đơn hàng và payment
            $orderCode = explode('_', $data['app_trans_id'])[1];
            $order     = Order::where('order_code', $orderCode)->first();

            if (! $order) {
                \Log::error('ZaloPay callback: Order not found', ['order_code' => $orderCode]);
                return response()->json([
                    'return_code'    => -1,
                    'return_message' => 'Không tìm thấy đơn hàng',
                ]);
            }

            $payment = Payment::where('transaction_id', $data['app_trans_id'])->first();
            if (! $payment) {
                \Log::error('ZaloPay callback: Payment not found', ['transaction_id' => $data['app_trans_id']]);
                return response()->json([
                    'return_code'    => -1,
                    'return_message' => 'Không tìm thấy giao dịch thanh toán',
                ]);
            }

            if ($data['status'] == 1) {
                $payment->update([
                    'status'                   => 'completed',
                    'payment_gateway_response' => json_encode($data),
                ]);

                $this->updateOrderPaymentStatus($order);
                DB::commit();

                return response()->json([
                    'return_code'    => 1,
                    'return_message' => 'success',
                ]);
            }

            $payment->update([
                'status'                   => 'failed',
                'payment_gateway_response' => json_encode($data),
            ]);

            DB::commit();
            return response()->json([
                'return_code'    => -1,
                'return_message' => 'Thanh toán thất bại',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ZaloPay callback exception', [
                'request' => $request->all(),
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'return_code'    => -1,
                'return_message' => 'Lỗi xử lý thanh toán',
            ]);
        }
    }

    /**
     * Kiểm tra trạng thái đơn hàng ZaloPay
     */
    public function zalopay_check_status(Request $request)
    {
        $app_id   = config('services.zalopay.app_id');
        $key1     = config('services.zalopay.key1');
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

}
