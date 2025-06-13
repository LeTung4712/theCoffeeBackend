<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_code',
        'user_id',
        'user_name',
        'mobile_no',
        'status',
        'payment_status',
        'address',
        'note',
        'total_price',
        'discount_amount',
        'shipping_fee',
        'final_price',
        'payment_method',
    ];

    protected $casts = [
        'total_price'     => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_fee'    => 'decimal:2',
        'final_price'     => 'decimal:2',
    ];

    public function user()
    { // Một đơn hàng thuộc về một người dùng
        return $this->belongsTo(User::class);
    }

    public function orderItems()
    { // Một đơn hàng có nhiều sản phẩm
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    { // Một đơn hàng có nhiều giao dịch thanh toán
        return $this->hasMany(Payment::class);
    }

    /**
     * Kiểm tra xem đơn hàng đã thanh toán đủ hay chưa
     */
    public function isPaid()
    {
        return $this->payment_status == '1';
    }

    public function getTotalPaidAmount()
    {
        return $this->payments()
            ->where('status', '1')   // 1 = thành công
            ->first()?->amount ?? 0; // Lấy payment thành công đầu tiên
    }

}
