<?php
namespace App\Http\Controllers\RecommenderSystem;

use App\Data\TransactionData;
use App\Http\Controllers\Controller;
use App\Models\AssociationRule;
use App\Models\Product;
use App\Services\AprioriService;
use App\Services\FP_GrowthService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    //thuật toán thống kê hành vi mua sắm
    public function getAnalyzeShoppingBehavior(Request $request)
    {
        // Bắt đầu đo thời gian
        $startTime = microtime(true);

        $algorithm     = $request->input('algorithm');
        $minSupport    = $request->input('minSupport');
        $minConfidence = $request->input('minConfidence');
        $timeRange     = $request->input('timeRange');
        $startDate     = $request->input('startDate');
        $endDate       = $request->input('endDate');

        // Lấy transactions từ TransactionData
        $transactions = TransactionData::getTransactions();

        //goi ham removeDuplicates de loai bo cac san pham trung lap trong moi giao dich
        $transactions = array_map([$this, 'removeDuplicates'], $transactions);

        if ($algorithm == 'apriori') {
            $aprioriService   = new AprioriService($minSupport, $minConfidence);
            $frequentItemsets = $aprioriService->findFrequentItemsets($transactions, $minSupport);
            $associationRules = $aprioriService->generateAssociationRules($frequentItemsets);
            $support          = $aprioriService->getSupports();
        } else if ($algorithm == 'fp_growth') {
            $fpGrowthService  = new FP_GrowthService($minSupport * count($transactions), $minConfidence);
            $frequentItemsets = $fpGrowthService->findFrequentItemsets($transactions);
            $associationRules = $fpGrowthService->generateAssociationRules($frequentItemsets);
        } else {
            return response()->json([
                'message' => 'Thuật toán không hợp lệ',
            ], 400);
        }

        // Kết thúc đo thời gian và tính thời gian thực thi (đơn vị milliseconds)
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        //bỏ tiền tố I vd: I1 -> 1 trong associationRules
        $associationRules = array_map(function ($rule) {
            return [
                str_replace('I', '', $rule[0]),
                str_replace('I', '', $rule[1]),
                $rule[2],
            ];
        }, $associationRules);

        //xóa hết dữ liệu bảng association_rules
        AssociationRule::truncate();

        //thêm vào bảng association_rules
        foreach ($associationRules as $rule) {
            $antecedent = $rule[0];
            $consequent = $rule[1];
            $confidence = $rule[2];
            AssociationRule::create([
                'antecedent' => $antecedent,
                'consequent' => $consequent,
                'confidence' => $confidence,
            ]);
        }

        //trả về đúng thông tin sp từ bảng product
        $rulesWithProducts = [];
        foreach ($associationRules as $rule) {
            $antecedent = explode(',', $rule[0]);
            $consequent = explode(',', $rule[1]);

            $antecedent_products = Product::whereIn('id', $antecedent)
                ->select('id', 'name', 'category_id', 'description', 'image_url', 'price', 'price_sale')
                ->get();

            $consequent_products = Product::whereIn('id', $consequent)
                ->select('id', 'name', 'category_id', 'description', 'image_url', 'price', 'price_sale')
                ->get();

            $rulesWithProducts[] = [
                'antecedent'          => $rule[0],
                'consequent'          => $rule[1],
                'confidence'          => $rule[2],
                'antecedent_products' => $antecedent_products,
                'consequent_products' => $consequent_products,
                'updated_at'          => now(),
            ];
        }

        if (count($associationRules) == 0) {
            return response()->json([
                'message' => 'Không có luật kết hợp nào',
            ], 404);
        } else {
            return response()->json([
                'message'           => 'Phân tích luật kết hợp thành công',
                'totalTransactions' => count($transactions),
                'totalRules'        => count($associationRules),
                'executionTime'     => $executionTime, // Thời gian thực thi (milliseconds)
                'frequentItemsets'  => $frequentItemsets,
                'associationRules'  => $rulesWithProducts,
            ], 200);
        }
    }

    //thuật toán đề xuất sản phẩm
    public function getRecommendations(Request $request)
    {
        // Lấy và kiểm tra dữ liệu đầu vào
        $cartItems = $request->input('cartItems', []);

        // Kiểm tra giỏ hàng rỗng
        if (empty($cartItems)) {
            return $this->getPopularProducts();
        }

        // Chuẩn hóa dữ liệu giỏ hàng
        $cartSet = collect($cartItems)
            ->map(function ($item) {
                return (string) $item;
            })
            ->sort()
            ->values()
            ->toArray();

        // Lấy luật kết hợp với ngưỡng confidence và giới hạn số lượng
        $rules = AssociationRule::where('confidence', '>=', 0.3)
            ->orderBy('confidence', 'desc')
            ->limit(500)
            ->get();

        $recommendations = [];

        // Duyệt qua các luật kết hợp và kiểm tra tập con
        foreach ($rules as $rule) {
            $antecedentArray = array_map('trim', explode(',', $rule->antecedent));

            // Kiểm tra xem antecedent có phải là tập con của giỏ hàng
            if ($this->isSubset($antecedentArray, $cartSet)) {
                $consequentId = $rule->consequent;

                // Kiểm tra xem sản phẩm đã có trong giỏ hàng chưa
                if (! in_array($consequentId, $cartSet)) {
                    if (! isset($recommendations[$consequentId])) {
                        $recommendations[$consequentId] = [
                            'confidence' => $rule->confidence,
                            'count'      => 1,
                        ];
                    } else {
                        $recommendations[$consequentId]['count']++;
                        $recommendations[$consequentId]['confidence'] = max(
                            $recommendations[$consequentId]['confidence'],
                            $rule->confidence
                        );
                    }
                }
            }
        }

        // Nếu không có đề xuất, trả về sản phẩm phổ biến
        if (empty($recommendations)) {
            return $this->getPopularProducts();
        }

        // Tính điểm cho mỗi đề xuất dựa trên confidence và số lần xuất hiện
        $scored_recommendations = [];
        foreach ($recommendations as $productId => $data) {
            $scored_recommendations[$productId] = $data['confidence'] * (1 + 0.1 * log($data['count']));
        }

        // Sắp xếp theo điểm số giảm dần
        arsort($scored_recommendations);

        // Lấy tối đa 6 sản phẩm được đề xuất
        $recommendedIds = array_slice(array_keys($scored_recommendations), 0, 6);

        // Lấy thông tin sản phẩm trong một query để tránh N+1
        $recommended_products = Product::whereIn('id', $recommendedIds)
            ->get()
            ->sortBy(function ($product) use ($recommendedIds) {
                return array_search($product->id, $recommendedIds);
            })
            ->values();

        if ($recommended_products->isEmpty()) {
            return $this->getPopularProducts();
        }

        return response()->json([
            'message'            => 'Lấy danh sách sản phẩm đề xuất thành công',
            'recommend_id'       => $recommendedIds,
            'recommend_products' => $recommended_products,
        ], 200);
    }

    // Hàm hỗ trợ kiểm tra tập con
    private function isSubset($subset, $set): bool
    {
        return empty(array_diff($subset, $set));
    }

    // Hàm lấy sản phẩm phổ biến
    private function getPopularProducts()
    {
        $popularProducts = Product::withCount('orderItems')
            ->orderBy('order_items_count', 'desc')
            ->take(6)
            ->get();

        return response()->json([
            'message'            => 'Đề xuất dựa trên sản phẩm phổ biến',
            'recommend_products' => $popularProducts,
        ], 200);
    }

    //lấy danh sách luật kết hợp
    public function getAssociationRules()
    {
        $associationRules = AssociationRule::all();

        if (count($associationRules) == 0) {
            return response()->json([
                'message' => 'Không có luật kết hợp nào',
            ], 404);
        }

        //lấy thông tin id, tên , giá của sản phẩm antecedent và consequent của từng luật kết hợp
        //vd antecedent: 1,2,3 => laays thông tin của sản phẩm có id 1,2,3

        foreach ($associationRules as $rule) {
            $antecedent          = explode(',', $rule->antecedent);
            $consequent          = explode(',', $rule->consequent);
            $antecedent_products = [];
            foreach ($antecedent as $id) {
                $product = Product::find($id);
                if ($product) {
                    $antecedent_products[] = $product;
                }
            }
            $consequent_products = [];
            foreach ($consequent as $id) {
                $product = Product::find($id);
                if ($product) {
                    $consequent_products[] = $product;
                }
            }
            $rule->antecedent_products = $antecedent_products;
            $rule->consequent_products = $consequent_products;
            unset($rule->created_at);
            //unset($rule->updated_at);
        }

        return response()->json([
            'message'          => 'Lấy danh sách luật kết hợp thành công',
            'associationRules' => $associationRules,
        ], 200);

    }

    //hàm loại bỏ các sản phẩm trùng lặp trong mỗi giao dịch
    private function removeDuplicates($transaction)
    {
        return array_unique($transaction);
    }

    private function getTransactions()
    {
        return TransactionData::getTransactions();
    }

}
