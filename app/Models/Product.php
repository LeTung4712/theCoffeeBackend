<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [ // cho phep nhung cot nao duoc phep insert du lieu
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
}
