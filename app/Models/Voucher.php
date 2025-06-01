<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'image_url',
        'description',
        'discount_type',       //  percent,  amount
        'discount_percent',    //nếu discount_type là percent thì discount_percent là giá trị % giảm giá,không là null
        'max_discount_amount', //giá trị giảm tối đa
        'min_order_amount',    //giá trị đơn hàng tối thiểu
        'expire_at',           //ngày hết hạn
        'total_quantity',      //tổng số lượng
        'used_quantity',       //số lượng đã sử dụng
        'active',              //trạng thái
        'limit_per_user',      //số lượng mỗi user có thể sử dụng
    ];

    protected $casts = [
        'discount_percent'    => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_order_amount'    => 'decimal:2',
    ];

    //mỗi voucher có thể được sử dụng bởi nhiều user
    public function voucherUsages()
    {
        return $this->hasMany(VoucherUsage::class, 'voucher_id', 'id');
    }
}
