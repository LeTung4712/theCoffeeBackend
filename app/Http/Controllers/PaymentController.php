<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class PaymentController extends Controller
{
    //thanh toán qua momo
    function execPostRequest($url, $data){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data))
        );
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        //execute post
        $result = curl_exec($ch); 
        //close connection
        curl_close($ch);
        return $result; 
    }   

    public function momo_payment(Request $request)
    {
        $order = Order::find($request->order_id);
        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create"; 

        $partnerCode = 'MOMOBKUN20180529'; 
        $accessKey = 'klm05TvNBzhg7h7j'; 
        $secretKey = 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa';
        $orderInfo = "Thanh toán qua MoMo";
        $amount = $request->final_price;
        $orderId = $request->order_code;
        $redirectUrl = "$request->returnUrl"; //đây là url đến trang của merchant, khi người dùng click vào nút thanh toán trên app MoMo
        $ipnUrl = "$request->returnUrl"; //đây là url dùng để MoMo gửi kết quả thanh toán về cho merchant
        $extraData = "";

        $requestId = time() . "";
        $requestType = "captureWallet";
        $rawHash = "accessKey=" . $accessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&ipnUrl=" . $ipnUrl . "&orderId=" . $orderId . "&orderInfo=" . $orderInfo . "&partnerCode=" . $partnerCode . "&redirectUrl=" . $redirectUrl . "&requestId=" . $requestId . "&requestType=" . $requestType;
        $signature = hash_hmac("sha256", $rawHash, $secretKey);
        $data = array(
            'partnerCode' => $partnerCode,
            'partnerName' => "Test",
            "storeId" => "MomoTestStore",
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature
        );

        try {
            $result = $this->execPostRequest($endpoint, json_encode($data));
            $jsonResult = json_decode($result, true);  // decode json

            return response()->json(['payUrl' => $jsonResult['payUrl']], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gửi yêu cầu thanh toán thất bại', 'error' => $e->getMessage()], 400);
        }
    }
}
