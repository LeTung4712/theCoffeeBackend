<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'parent_id',
        'image_url',
        'active',
        ];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id', 'id');
    }

    public function parent() //hàm này để lấy ra category cha của category con
    {
        return $this->belongsTo(Category::class, 'parent_id', 'id');
    }
}
