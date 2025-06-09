<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherUsage extends Model
{
    use HasFactory;
    protected $fillable = [
        'voucher_id',
        'user_id',
        'order_id',
    ];

    //mỗi voucher có thể được sử dụng bởi nhiều user
    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id', 'id');
    }

    //mỗi user có thể sử dụng nhiều voucher
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    //mỗi voucher usage thuộc về một đơn hàng
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
