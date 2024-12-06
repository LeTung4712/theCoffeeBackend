<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ToppingProduct extends Model
{
    use HasFactory;
    protected $fillable = [
        'topping_id',
        'product_id'
    ];

    protected $casts = [ //chuyển đổi dữ liệu từ kiểu string sang kiểu array
        'topping_id' =>'array'
    ];
    
    public function product()
    {   // Một sản phẩm có thể có nhiều topping
        return $this->hasOne(Product::class, 'id', 'product_id');
    }

    public function topping()
    {   // Một sản phẩm có thể có nhiều topping
        return $this->hasOne(Topping::class, 'id', 'topping_id');
    }
}
