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
        return response ([
            'Toppings' => $toppings,
        ], 200);
    }
    //them topping
    public function create(Request $request){
        if (Topping::where('name', $request->name)->first()) { 
            return response([
                'message' => 'Đã có sản phẩm này',
                'request' => $request ->name,
                'price' => $request ->price,

            ]);
        }
        $topping =Topping::create([ //
            'name' => $request->name,
            'price' => $request->price,
        ]);
        return response([
            'message' => 'Thêm thành công',
            'topping' => $topping,
        ], 200);

    }
    //cap nhat topping
    public function update(Request $request){
        $topping=Topping::find($request->id);
        $topping->name=$request->name;
        $topping->price = $request->price;
        $topping->save();
        return response([
            'message' => 'Cập nhật thành công',
            'topping' => $topping,
        ], 200);
    }
    //xoa topping
    public function delete(Request $request){
        $topping=Topping::find($request->id);
        $topping->delete();
        return response([
            'message' => 'Xóa thành công',
            'topping' => $topping,
        ], 200);
    }
}
