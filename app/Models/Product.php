<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'category_id',
        'description',
        'price',
        'price_sale',
        'active',
        'image_url'
    ];

    public function Category() 
    {
        return $this->hasOne(Category::class, 'id', 'category_id') // trả về 1 category có id = category_id của product đó 
            ->withDefault(['name' => '']); // nếu không có category thì trả về name = ''
    }

    public function toppingProducts()
    {
        return $this->hasMany(ToppingProduct::class, 'product_id', 'id'); 
    }

    public function toppings()
    {
        $toppingProducts = $this->toppingProducts; // Lấy tất cả topping_product liên quan
        $toppingIds = [];

        foreach ($toppingProducts as $toppingProduct) {
            $toppingIds = array_merge($toppingIds, $toppingProduct->topping_id); // Gộp tất cả topping_id vào mảng
        }

        return Topping::whereIn('id', $toppingIds)->where('active', 1)->get(); // Lấy topping từ bảng Topping
    }
}
