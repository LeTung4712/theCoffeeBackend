<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_code',
        'amount',
        'payment_method',
        'status',
        'transaction_id',
        'payment_gateway_response',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'status'       => 'integer',
    ];

    /**
     * Lấy đơn hàng liên quan đến giao dịch thanh toán này
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
