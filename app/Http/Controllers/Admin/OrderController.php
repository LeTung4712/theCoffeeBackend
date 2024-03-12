<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AddressNote;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin', ['except' => ['addOrder', 'getOrders', 'getOrderInfo']]);
        if (!auth('admin')->check()) { //
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
    }
    //them don hang
    public function addOrder(Request $request)
    {
        try {
            $order = Order::create([
                'user_id' => (int) $request->user_id,
                'user_name' => $request->user_name,
                'mobile_no' => $request->mobile_no,
                'order_time' => Carbon::now(),
                'address' => $request->address,
                'note' => $request->note,
                'shipcost' => '15000',
                'total_price' => $request->total_price,
                'payment_method' => $request->payment_method,
            ]);
            $order->order_id = "TCH" . time() . "" . $order->id;
            $order->save();
            if (!AddressNote::where('user_id', $request->user_id) 
                ->where('address', $request->address)
                ->exists()) {
                AddressNote::create([
                    'user_id' => (int) $request->user_id,
                    'user_name' => $request->user_name,
                    'address' => $request->address,
                    'mobile_no' => $request->mobile_no,
                ]);
            }
            foreach ($request->products as $product) {
                OrderItem::create([
                    'order_id' => $order->order_id,
                    'product_id' => $product['product_id'],
                    'product_count' => $product['product_count'],
                    'topping_id' => $product['topping_id'],
                    'topping_count' => $product['topping_count'],
                    'size' => $product['size'],
                    'price' => $product['price'],
                ]);
            }
            return response([
                'error' => false,
                'order_id' => $order->order_id,
            ]);
        } catch (\Exception $err) {
            return response([
                'error' => true,
                'message' => $err->getMessage(),
                'order_id' => null,
            ]);
        }
    }
    //don hang da thanh toan
    public function paidOrder(Request $request){
        $order=Order::where('order_id',$request->order_id)->first();
        $order->state=0; 
        $order->save();
        return $order;
    }
    //dong y don hang
    public function acceptOrder(Request $request){
        $order=Order::where('order_id',$request->order_id)->first();
        $order->state=1;
        $order->save();
        return $order;
    }
    //huy don hang
    public function cancelOrder(Request $request){
        $order=Order::where('order_id',$request->order_id)->first();
        $order->state=-1;
        $order->save();
        return $order;
    }
    //xem cac don hang cuar user
    public function getOrders(Request $request){
        $orders = Order::where('user_id', $request->user_id)
                        ->orderby('id', 'desc') 
                        ->get();
        $productsOfOrder = collect();
        foreach($orders as $order){
            $productsOfOrder->push($this->getOrderItems($order->order_id));
        }
        return response([
            'orders' => $orders,
            'productsOfOrder' => $productsOfOrder,
        ]);
    }
    //lay thong tin don hang
    public function getOrderInfo(Request $request){
        $order = Order::where('order_id', $request->order_id)
                        ->first();
        $products = $this->getOrderItems($order->order_id);
        return response([
            'order_info' => $order,
            'productsOfOrder' => $products,
        ]);
    }
    //lay don hang thanh cong
    public function getSuccessOrders(){
        $orders = Order::where('state', 1)
                        ->orderby('id')
                        ->get();
        $productsOfOrder = collect();
        foreach($orders as $order){
            $productsOfOrder->push($this->getOrderItems($order->order_id));
        }
        return response([
            'orders' => $orders,
            'productsOfOrder' => $productsOfOrder,
        ]);
    }
    //lay don hang that bai
    public function getUnsuccessOrders(){
        $orders = Order::where('state', 0)
                        ->orderby('id')
                        ->get();
        $productsOfOrder = collect();
        foreach($orders as $order){
            $productsOfOrder->push($this->getOrderItems($order->order_id));
        }
        return response([
            'orders' => $orders,
            'productsOfOrder' => $productsOfOrder,
        ]);
    }
    //lay thong tin san pham
    public function getOrderItems($order_id){
        $orderItems = OrderItem::where('order_id', $order_id)
                                ->orderby('id')
                                ->get();
        foreach($orderItems as $item){
            $item->product_id=Product::select("name")->where("id",$item->product_id)->first();
            $item->topping_id=ToppingController::index($item->topping_id);
        }                     
        return $orderItems;
    }
}
