<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ToppingProduct extends Model
{
    use HasFactory;
    protected $fillable = [ //protected là phạm vi truy cập
        'topping_id',
        'product_id'
    ];

    protected $casts = [ //chuyển đổi dữ liệu từ kiểu string sang kiểu array
        'topping_id' =>'array'
    ];
    
    public function product(){
        return $this->hasOne(Product::class, 'id', 'product_id');
    }

    public function topping(){
        return $this->hasOne(Topping::class, 'id', 'topping_id');
    }
}
