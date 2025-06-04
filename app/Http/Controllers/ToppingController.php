<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Controller;
use App\Models\ToppingProduct;
use App\Models\Topping;


class ToppingController extends Controller
{
    //lay danh sach topping
    public static function index(){
        $toppings = Topping::orderby('id')->get();
        // Chuyển đổi các giá trị số sang dạng số cho từng topping
        foreach ($toppings as $topping) {
            $topping->price = (float) $topping->price;
        }
        return $toppings->isNotEmpty()
            ? response()->json([
                'status' => true,
                'message' => 'Lấy danh sách topping thành công',
                'data' => [
                    'toppings' => $toppings
                ]
            ], 200)
            : response()->json([
                'status' => false, 
                'message' => 'Không có topping'
            ], 404);
    }

    //lay danh sach topping active
    public static function indexActive(){
        $toppings = Topping::where('active', true)->get();
        return $toppings->isNotEmpty()
            ? response()->json([
                'status' => true,
                'message' => 'Lấy danh sách topping thành công',
                'data' => [
                    'toppings' => $toppings
                ]
            ], 200)
            : response()->json([
                'status' => false, 
                'message' => 'Không có topping'
            ], 404);
    }

    //them topping
    public function create(Request $request){
        $existingTopping = Topping::where('name', $request->name)->first();
        if ($existingTopping) {
            return response()->json([
                'status' => false, 
                'message' => 'Đã có topping này'
            ], 409);
        }

        try {
            $topping = Topping::create([
                'name' => (string) $request->name,
                'price' => (float) $request->price ?? 0,
                'active' => (bool) true
            ]);
            return response()->json([
                'status' => true,
                'message' => 'Thêm topping thành công',
                'data' => [
                    'topping' => $topping
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false, 
                'message' => 'Thêm topping thất bại', 
            ], 400);
        }
    }

    //cap nhat topping
    public function update(Request $request){
        $topping = Topping::find($request->id);
        if (!$topping) {
            return response()->json([
                'status' => false, 
                'message' => 'Không có topping này trong dữ liệu'
            ], 404);
        }

        try {
            $topping->name = (string) $request->name;
            $topping->price = (float) $request->price ?? 0;
            $topping->active = (bool) $request->active;
            $topping->save();
            return response()->json([
                'status' => true,
                'message' => 'Cập nhật topping thành công',
                'data' => [
                    'topping' => $topping
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false, 
                'message' => 'Cập nhật topping thất bại', 
            ], 400);
        }
    }
    
    //xoa topping
    public function delete(Request $request){
        $topping = Topping::find($request->id);
        if (!$topping) {
            return response()->json([
                'status' => false, 
                'message' => 'Không có topping này trong dữ liệu'
            ], 404);
        }

        try {
            $topping->delete();
            return response()->json([
                'status' => true,
                'message' => 'Xóa thành công',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false, 
                'message' => 'Xóa topping thất bại', 
            ], 400);
        }
    }
}
