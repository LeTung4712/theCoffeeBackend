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
    public function __construct()
    {
        $this->middleware('auth:admin', ['except' => ['index', 'getActiveToppings', 'create', 'update', 'delete']]);
        if (!auth('admin')->check()) { //
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
    }

    //lay danh sach topping
    public static function index(){
        $toppings = Topping::orderby('id')->get();
        // Chuyển đổi các giá trị số sang dạng số cho từng topping
        foreach ($toppings as $topping) {
            $topping->price = (float) $topping->price;
        }
        return $toppings->isNotEmpty()
            ? response()->json(['message' => 'Lấy danh sách topping thành công', 'toppings' => $toppings], 200)
            : response()->json(['message' => 'Không có topping'], 404);
    }

    //lay danh sach topping active
    public static function getActiveToppings(){
        $toppings = Topping::where('active', true)->get();
        return $toppings->isNotEmpty()
            ? response()->json(['message' => 'Lấy danh sách topping thành công', 'toppings' => $toppings], 200)
            : response()->json(['message' => 'Không có topping'], 404);
    }

    //them topping
    public function create(Request $request){
        $existingTopping = Topping::where('name', $request->name)->first();
        if ($existingTopping) {
            return response()->json(['message' => 'Đã có topping này'], 409);
        }

        try {
            $topping = Topping::create([
                'name' => (string) $request->name,
                'price' => (float) $request->price ?? 0,
                'active' => (bool) true
            ]);
            return response()->json(['message' => 'Thêm topping thành công', 'topping' => $topping], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Thêm topping thất bại', 'error' => $e->getMessage()], 400);
        }
    }

    //cap nhat topping
    public function update(Request $request){
        $topping = Topping::find($request->id);
        if (!$topping) {
            return response()->json(['message' => 'Không có topping này trong dữ liệu'], 404);
        }

        try {
            $topping->name = (string) $request->name;
            $topping->price = (float) $request->price ?? 0;
            $topping->active = (bool) $request->active;
            $topping->save();
            return response()->json(['message' => 'Cập nhật topping thành công', 'topping' => $topping], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cập nhật topping thất bại', 'error' => $e->getMessage()], 400);
        }
    }
    
    //xoa topping
    public function delete(Request $request){
        $topping = Topping::find($request->id);
        if (!$topping) {
            return response()->json(['message' => 'Không có topping này trong dữ liệu'], 404);
        }

        try {
            $topping->delete();
            return response()->json(['message' => 'Xóa thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Xóa topping thất bại', 'error' => $e->getMessage()], 400);
        }
    }
}
