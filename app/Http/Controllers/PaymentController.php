<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'status'                   => $status, // 0: pending, 1: completed, 2: failed, 3: cancelled
            'transaction_id'           => $transactionId,
            'payment_gateway_response' => $gatewayResponse ? json_encode($gatewayResponse) : null,
        ]);
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
        \Log::info('MOMO payment request', $request->all());
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
            $config = [
                'endpoint'    => config('services.momo.endpoint'),
                'partnerCode' => config('services.momo.partner_code'),
                'accessKey'   => config('services.momo.access_key'),
                'secretKey'   => config('services.momo.secret_key'),
                'storeId'     => config('services.momo.store_id'),
            ];
            \Log::info('MOMO config', $config);
            // Tạo mã giao dịch duy nhất
            $uniqueTransactionId = $order->order_code . '_' . time();

            // Tạo payment record
            $payment                 = $this->createPayment($order, $order->final_price, 'momo');
            $payment->transaction_id = $uniqueTransactionId;
            $payment->save();

            // Tạo payload cho request
            $payload = [
                'partnerCode' => $config['partnerCode'],
                'partnerName' => env('APP_NAME', 'Test'),
                'storeId'     => $config['storeId'],
                'requestId'   => time() . "",
                'amount'      => (int) $order->final_price,
                'orderId'     => $uniqueTransactionId,
                'orderInfo'   => "Thanh toán đơn hàng " . $order->order_code,
                'redirectUrl' => $request->return_url,
                'ipnUrl'      => 'https://coffee-shop.click/api/v1/payments/momo/callback',
                'lang'        => 'vi',
                'extraData'   => $payment->id,
                'requestType' => "captureWallet",
            ];

            // Tạo chữ ký
            $rawHash = "accessKey={$config['accessKey']}&amount={$payload['amount']}&extraData={$payload['extraData']}"
                . "&ipnUrl={$payload['ipnUrl']}&orderId={$payload['orderId']}&orderInfo={$payload['orderInfo']}"
                . "&partnerCode={$payload['partnerCode']}&redirectUrl={$payload['redirectUrl']}"
                . "&requestId={$payload['requestId']}&requestType={$payload['requestType']}";

            $payload['signature'] = hash_hmac("sha256", $rawHash, $config['secretKey']);

            // Gọi API MOMO
            $result     = $this->execPostRequest($config['endpoint'], json_encode($payload));
            $jsonResult = json_decode($result, true);

            if (! isset($jsonResult['payUrl'])) {
                \Log::error('MOMO payment error', [
                    'order_code' => $order->order_code,
                    'response'   => $jsonResult,
                ]);

                $payment->update([
                    'status'                   => '2', // failed
                    'payment_gateway_response' => json_encode($jsonResult),
                ]);

                DB::commit();
                return response()->json([
                    'status'  => false,
                    'message' => 'Không thể tạo yêu cầu thanh toán Momo',
                    'details' => $jsonResult,
                ], 400);
            }

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
                'message' => 'Có lỗi xảy ra khi xử lý thanh toán Momo',
            ], 500);
        }
    }

    /**
     * Xử lý callback từ Momo
     */
    public function momo_callback(Request $request)
    {
        \Log::info('MOMO callback received', $request->all());

        $secretKey     = config('services.momo.secret_key');
        $momoSignature = $request->signature;

        // The raw hash string for signature verification. DO NOT CHANGE THE ORDER.
        // The order of fields is specified by Momo's documentation for IPN.
        $rawHash = "accessKey=" . config('services.momo.access_key') .
        "&amount=" . $request->amount .
        "&extraData=" . $request->extraData .
        "&message=" . $request->message .
        "&orderId=" . $request->orderId .
        "&orderInfo=" . $request->orderInfo .
        "&orderType=" . $request->orderType .
        "&partnerCode=" . $request->partnerCode .
        "&payType=" . $request->payType .
        "&requestId=" . $request->requestId .
        "&responseTime=" . $request->responseTime .
        "&resultCode=" . $request->resultCode .
        "&transId=" . $request->transId;

        $signature = hash_hmac("sha256", $rawHash, $secretKey);

        if ($signature !== $momoSignature) {
            \Log::error('MOMO callback: Invalid signature', ['request' => $request->all()]);
            // Just log and stop processing. Do not return error response to prevent retry storms.
            return;
        }

        DB::beginTransaction();
        try {
            $paymentId = $request->extraData;
            $payment   = Payment::find($paymentId);

            if (! $payment) {
                \Log::error('MOMO callback: Payment not found', ['payment_id' => $paymentId]);
                DB::rollBack();
                return; // Stop processing
            }

            // Idempotency Check: if already completed, do nothing.
            if ($payment->status === '1') {
                \Log::info('MOMO callback: Payment already completed.', ['payment_id' => $paymentId]);
                DB::commit(); // Commit to release lock
                return response()->json(['code' => 0, 'message' => 'OK']);
            }

            $order = $payment->order;
            if (! $order) {
                \Log::error('MOMO callback: Order not found for payment', ['payment_id' => $paymentId]);
                DB::rollBack();
                return; // Stop processing
            }

            $resultCode = $request->resultCode;

            // Update payment record
            $payment->update([
                'status'                   => ($resultCode == '0') ? '1' : '2', // 1: completed, 2: failed
                'transaction_id'           => $request->transId,
                'payment_gateway_response' => json_encode($request->all()),
            ]);

            // Update order status if payment was successful
            if ($resultCode == '0') {
                $order->update(['payment_status' => '1']); // 1 = đã thanh toán
            } else {
                $order->update(['payment_status' => '0']); // 0 = chưa thanh toán
            }

            DB::commit();

            // Respond to Momo to acknowledge receipt
            return response()->json(['code' => 0, 'message' => 'OK']);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('MOMO callback exception', [
                'request' => $request->all(),
                'error'   => $e->getMessage(),
            ]);
            // Just log the error. Do not return a 500 error to Momo, as it might retry.
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
                'message' => 'Có lỗi xảy ra khi xử lý thanh toán VNPay',
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
                    'status'                   => '1', // completed
                    'payment_gateway_response' => json_encode($inputData),
                ]);

                $order->update([
                    'payment_status' => '1', // 1 = đã thanh toán
                ]);

                DB::commit();

                return redirect()->away(env('FRONTEND_URL') . '/payment/result?status=success&order_code=' . $orderCode);
            }

            // Thanh toán thất bại
            $payment->update([
                'status'                   => '2', // failed
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
            $config = [
                "app_id"   => 2553,
                "key1"     => "PcY4iZIKFCIdgZvA6ueMcMHHUbRLYjPL",
                "key2"     => "kLtgPl8HHhfvMuDHPwKfgfsY4Ydm9eIz",
                "endpoint" => "https://sb-openapi.zalopay.vn/v2/create",
            ];

            // Tạo payment record
            $payment                 = $this->createPayment($order, $order->final_price, 'zalopay');
            $payment->transaction_id = date("ymd") . "_" . $order->order_code;
            $payment->save();

            // Chuẩn bị dữ liệu gửi đi
            $embeddata = json_encode([
                'merchantinfo' => 'embeddata123',
                'redirecturl'  => $request->return_url,
            ]);

            $items = json_encode([[
                'name'     => 'Thanh toan don hang ' . $order->order_code,
                'price'    => (int) $order->final_price,
                'quantity' => 1,
            ]]);

            $order_data = [
                "app_id"       => $config["app_id"],
                "app_time"     => round(microtime(true) * 1000),
                "app_trans_id" => $payment->transaction_id,
                "app_user"     => "user_" . $user->id,
                "item"         => $items,
                "embed_data"   => $embeddata,
                "amount"       => (int) $order->final_price,
                "description"  => "Thanh toan don hang " . $order->order_code,
                "bank_code"    => "zalopayapp",
                "callback_url" => 'https://coffee-shop.click/api/v1/payments/zalopay/callback',
            ];

            // Tạo chuỗi data để tính MAC
            $data = $order_data["app_id"] . "|" .
                $order_data["app_trans_id"] . "|" .
                $order_data["app_user"] . "|" .
                $order_data["amount"] . "|" .
                $order_data["app_time"] . "|" .
                $order_data["embed_data"] . "|" .
                $order_data["item"];

            $order_data["mac"] = hash_hmac("sha256", $data, $config["key1"]);

            // Log dữ liệu gửi đi để debug
            \Log::info('ZaloPay request data', [
                'order_data' => $order_data,
                'mac_data'   => $data,
            ]);

            // Gọi API ZaloPay
            $context = stream_context_create([
                "http" => [
                    "header"  => "Content-type: application/x-www-form-urlencoded\r\n",
                    "method"  => "POST",
                    "content" => http_build_query($order_data),
                ],
            ]);

            $resp   = file_get_contents($config["endpoint"], false, $context);
            $result = json_decode($resp, true);

            // Log response để debug
            \Log::info('ZaloPay response', [
                'response' => $result,
            ]);

            if ($result['return_code'] == 1) {
                // Chỉ cập nhật response, không cập nhật status. Status vẫn là 'pending'
                $payment->update([
                    'payment_gateway_response' => json_encode($result),
                ]);

                DB::commit();
                return response()->json([
                    'status'  => true,
                    'message' => 'Gửi yêu cầu thanh toán thành công',
                    'data'    => [
                        'payUrl'      => $result['order_url'],
                        'order_token' => $result['order_token'],
                    ],
                ], 200);
            }

            // Nếu việc tạo yêu cầu thanh toán thất bại
            $payment->update([
                'status'                   => '2', // failed
                'payment_gateway_response' => json_encode($result),
            ]);

            DB::commit();
            \Log::error('ZaloPay payment failed', [
                'order_code' => $request->order_code,
                'error'      => $result['return_message'] ?? 'Không thể tạo đơn hàng ZaloPay',
                'details'    => $result,
            ]);
            return response()->json([
                'status'  => false,
                'message' => $result['return_message'] ?? 'Không thể tạo đơn hàng ZaloPay',
                'details' => $result,
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ZaloPay payment exception', [
                'order_code' => $request->order_code,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Có lỗi xảy ra khi xử lý thanh toán ZaloPay',
            ], 500);
        }
    }

    /**
     * Xử lý callback từ ZaloPay
     */
    public function zalopay_callback(Request $request)
    {
        \Log::info('ZaloPay callback received', ['content' => $request->getContent()]);

        $result = [];
        try {
            // ZaloPay sends a JSON payload, not form-data.
            $key2         = config('services.zalopay.key2');
            $callbackData = json_decode($request->getContent(), true);
            $mac          = $callbackData['mac'];
            $data         = $callbackData['data'];

            $mac_verify = hash_hmac('sha256', $data, $key2);

            // 1. Signature Verification
            if ($mac_verify !== $mac) {
                \Log::error('ZaloPay callback: Invalid signature.');
                $result['return_code']    = -1;
                $result['return_message'] = 'Invalid signature';
                return response()->json($result);
            }

            DB::beginTransaction();

            $data_result  = json_decode($data, true);
            $app_trans_id = $data_result['app_trans_id'];

            // 2. Find Payment by transaction_id
            $payment = Payment::where('transaction_id', $app_trans_id)->first();

            if (! $payment) {
                \Log::error('ZaloPay callback: Payment not found', ['transaction_id' => $app_trans_id]);
                DB::rollBack();
                $result['return_code']    = -1;
                $result['return_message'] = 'Payment not found';
                return response()->json($result);
            }

            // 3. Idempotency Check
            if ($payment->status === '1') {
                \Log::info('ZaloPay callback: Payment already completed.', ['payment_id' => $payment->id]);
                DB::commit();
                $result['return_code']    = 1;
                $result['return_message'] = 'Success';
                return response()->json($result);
            }

            $order = $payment->order;
            if (! $order) {
                \Log::error('ZaloPay callback: Order not found for payment', ['payment_id' => $payment->id]);
                DB::rollBack();
                $result['return_code']    = -1;
                $result['return_message'] = 'Order not found';
                return response()->json($result);
            }

                                               // 4. Update Status
            if ($data_result['status'] == 1) { // Payment successful
                $payment->update([
                    'status'                   => '1',                    // completed
                    'payment_gateway_response' => $request->getContent(), // Store raw callback JSON
                ]);

                $order->update([
                    'payment_status' => '1', // 1 = đã thanh toán
                ]);

                DB::commit();
                $result['return_code']    = 1;
                $result['return_message'] = 'Success';
            } else {
                // Payment failed
                $payment->update([
                    'status'                   => '2', // failed
                    'payment_gateway_response' => $request->getContent(),
                ]);
                DB::commit();
                // Even if it fails, we acknowledge the callback.
                $result['return_code']    = 1;
                $result['return_message'] = 'Success';
            }

            return response()->json($result);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            \Log::error('ZaloPay callback exception', [
                'request' => $request->getContent(),
                'error'   => $e->getMessage(),
            ]);
            $result['return_code']    = -1;
            $result['return_message'] = 'Exception';
            return response()->json($result);
        }
    }
}
