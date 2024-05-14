<?php

namespace App\Http\Controllers\RecommenderSystem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AprioriService;

class AprioriController extends Controller
{
    public function analyzeApriori()
    {
        $minSupport = 0.2; // Minimum support threshold nghĩa là một mẫu phổ biến phải xuất hiện trong ít nhất 10% số giao dịch
        $confidence = 0.7;

        $aprioriService = new AprioriService($minSupport , $confidence);
        $frequentItemsets = $aprioriService->findFrequentItemsets($this->transactions, $minSupport);
        $associationRules = $aprioriService->generateAssociationRules($frequentItemsets); 
        $support = $aprioriService->getSupports();
        return response()->json([
            'count transactions' => count($this->transactions),
            'frequentItemsets' => $frequentItemsets,
            //'supports' => $support,
            'associationRules' => $associationRules
            ]);

    }
    //hàm lấy ra các sản phẩm có cùng order_id rồi trả về dạng json : order_id -> [product_id 1, product_id 2, ...]
    public function getTransactions()
    {
        $orderItems = OrderItem::select('order_id', 'product_id')->get();
        if ($orderItems->isEmpty()) {
            return response()->json(['message' => 'No data']);
        }else{
            return response()->json(['message'=> $orderItems]);
        }
        $transactions = [];
        foreach ($orderItems as $orderItem) {
            $transactions[$orderItem->order_id][] = $orderItem->product_id;
        }
        return response()->json(['data' => $transactions]);
    }

    private $transactions = [
        ['I1', 'I2', 'I5'],
        ['I2', 'I4'],
        ['I2', 'I3'],
        ['I1', 'I3', 'I2', 'I4'],
        ['I1', 'I3'],
        ['I2', 'I3'],
        ['I1', 'I3'],
        ['I1', 'I2', 'I3', 'I5'],
        ['I1', 'I2', 'I3']
    ];
}
