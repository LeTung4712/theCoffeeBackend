<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topping extends Model
{
    use HasFactory;
    protected $fillable =[
        'name',
        'price',
        'active'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function orderItems()
    {   // Một topping có thể thuộc nhiều sản phẩm
        return $this->belongsToMany(OrderItem::class, 'order_item_topping', 'topping_id', 'order_item_id');
    }

}
