<?php

namespace App\Http\Controllers\Admin;

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
        if(!$toppings){
            return response([
                'message' => 'Không có topping nào',
            ], 500);
        }
        return response ([
            'Toppings' => $toppings,
        ], 200);
    }
    //them topping
    public function create(Request $request){
        if (Topping::where('name', $request->name)->first()) { 
            return response([
                'message' => 'Đã có topping này',
                'request' => $request ->name,
                'price' => $request ->price,

            ]);
        }
        $topping =Topping::create([ //
            'name' => $request->name,
            'price' => $request->price,
        ]);
        if(!$topping){
            return response([
                'message' => 'Thêm topping thất bại',
            ], 500);
        }
        return response([
            'message' => 'Thêm topping thành công',
            'topping' => $topping,
        ], 200);

    }
    //cap nhat topping
    public function update(Request $request){
        $topping=Topping::find($request->id);
        $topping->name=$request->name;
        $topping->price = $request->price;
        $topping->save();
        if(!$topping){
            return response([
                'message' => 'Cập nhật topping thất bại',
            ], 500);
        }
        return response([
            'message' => 'Cập nhật topping thành công',
            'topping' => $topping,
        ], 200);
    }
    //xoa topping
    public function delete(Request $request){
        $topping=Topping::find($request->id);
        $topping->delete();
        if(!$topping){
            return response([
                'message' => 'Xóa topping thất bại',
            ], 500);
        }
        return response([
            'message' => 'Xóa thành công',
        ], 200);
    }
}
