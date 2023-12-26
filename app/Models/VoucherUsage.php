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
    ];

    public function voucher(){
        return $this->hasOne(Voucher::class, 'id', 'voucher_id');
    }
    public function user(){
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
