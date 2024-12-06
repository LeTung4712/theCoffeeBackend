<?php

namespace App\Http\Controllers\RecommenderSystem;

use App\Http\Controllers\Controller;
use App\Models\AssociationRule;
use App\Models\Product;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function getRecommendations(Request $request)
    {
        $cartItems = $request->input('cartItems');
        // sort() để đảm bảo rằng các sản phẩm trong giỏ hàng được sắp xếp theo thứ tự tăng dần
        $cartSet = collect($cartItems)->sort()->join(',');
        //dd($cartSet);
        $recommendations = [];
        $rules = AssociationRule::all();
        //dd($rules);
        foreach ($rules as $rule) { // duyệt qua tất cả các luật kết hợp
            $antecedent = collect(explode(',', $rule->antecedent))->sort()->join(',');
            if (strpos($cartSet, $antecedent) !== false) { // nếu tập hợp antecedent của luật kết hợp tồn tại trong giỏ hàng
                if (!isset($recommendations[$rule->consequent])) { // nếu sản phẩm consequent chưa được đề xuất
                    $recommendations[$rule->consequent] = $rule->confidence; // thêm sản phẩm consequent vào mảng đề xuất
                } else {
                    $recommendations[$rule->consequent] = max($recommendations[$rule->consequent], $rule->confidence); // cập nhật độ tin cậy của sản phẩm consequent
                }
            }
        }

        // sắp xếp mảng đề xuất theo độ tin cậy giảm dần
        arsort($recommendations);

        //lấy thông tin sản phẩm có id trong mảng đề xuất
        $recommend_products = [];
        for ($i = 0; $i < 6; $i++) {
            if (isset(array_keys($recommendations)[$i])) {
                $product = Product::find(array_keys($recommendations)[$i]);
                if ($product) {
                    $recommend_products[] = $product;
                }
            }
        }

        //nếu không có sản phẩm nào
        if (count($recommend_products) == 0) {
            return response()->json([
                'message' => 'Không có sản phẩm nào được đề xuất',
            ], 200);
        }

        return response()->json([
            'message' => 'Lấy danh sách sản phẩm đề xuất thành công',
            'recommend_id' => array_keys($recommendations),
            'recommend_products' => $recommend_products,
        ], 200);
    }

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
            $antecedent = explode(',', $rule->antecedent);
            $consequent = explode(',', $rule->consequent);
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
            unset($rule->updated_at);
        }

        
        return response()->json([
            'message' => 'Lấy danh sách luật kết hợp thành công',
            'associationRules' => $associationRules,
        ], 200);

    }

}
