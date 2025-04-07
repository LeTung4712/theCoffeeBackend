<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

            // Nếu đơn hàng đang ở trạng thái "pending" thì cập nhật thành "confirmed"
            if ($order->status === '0') {
                $order->status = '1';
            }
        } else {
            $order->payment_status = '0'; // Chưa thanh toán
        }

        $order->save();

        return $order;
    }

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
                'status' => 'failed',
                'notes'  => $e->getMessage(),
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
        $order->status         = '3'; // Đã hoàn thành
        $order->save();

        return response()->json([
            'message'    => 'Đã cập nhật thanh toán COD thành công',
            'order_code' => $order->order_code,
        ], 200);
    }
}
