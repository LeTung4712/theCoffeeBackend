<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_price',
        'product_quantity',
        'topping_items',
        'size',
        'item_note'
    ];

    protected $casts = [
        //có tác dụng là khi lấy dữ liệu từ database ra thì nó sẽ tự động chuyển dữ liệu từ dạng json sang dạng mảng
        'topping_id' => 'array',
        'topping_count' => 'array'
    ];
    
    public function order()
    {   // Một sản phẩm thuộc về một đơn hàng
        return $this->hasOne(Order::class, 'id', 'order_id');
    }

    public function product()
    { 
        return $this->hasOne(Product::class, 'id', 'product_id');
    }
}
