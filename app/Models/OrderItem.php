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
        'item_note',
    ];

    protected $casts = [
        'product_price'    => 'decimal:2',
        'topping_items'    => 'array',
        'product_quantity' => 'integer',
    ];

    public function order()
    { // Một sản phẩm thuộc về một đơn hàng
        return $this->hasOne(Order::class, 'id', 'order_id');
    }

    public function product()
    {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }
}
