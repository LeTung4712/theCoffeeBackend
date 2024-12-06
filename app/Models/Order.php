<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_code',
        'user_id',
        'user_name',
        'mobile_no',
        'status',
        'address',
        'note',
        'total_price',
        'discount_amount',
        'shipping_fee',
        'final_price',
        'payment_method',
        'order_time',
    ];

    public function user()
    {   // Một đơn hàng thuộc về một người dùng
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function orderItems()
    {   // Một đơn hàng có nhiều sản phẩm
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }
}
