<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voucher;
use App\Models\VoucherUsage;

class VoucherController extends Controller
{
    //lay danh sach voucher
    public function index()
    {
        $voucherList = Voucher::where('active', 1)
            ->orderby('id')
            ->get();
        if (!$voucherList) {
            return response([
                'message' => 'Không có voucher nào',
            ], 500);
        }
        return response([
            'message' => 'Lấy danh sách voucher thành công',
            'vouchers' => $voucherList,
        ], 200);
    }
    //them voucher
    public function create(Request $request)
    {
        if (Voucher::where('code', $request->code)->first()) {
            return response([
                'message' => 'Đã có voucher này',
            ]);
        }
        $voucher = Voucher::create([
            'code' => $request->code,
            'image_url' => $request->image_url,
            'description' => $request->description,
            'discount_type' => $request->discount_type,
            'discount_percent' => $request->discount_percent,
            'max_discount_amount' => $request->max_discount_amount,
            'min_order_amount' => $request->min_order_amount,
            'expire_at' => $request->expire_at,
            'total_quantity' => $request->total_quantity,
            'used_quantity' => $request->used_quantity,
            'active' => 1,
        ]);
        if (!$voucher) {
            return response([
                'message' => 'Thêm voucher thất bại',
            ], 500);
        }
        return response([
            'message' => "Thêm voucher thành công",
            'voucher' => $voucher,
        ], 200);
    }
    //sua voucher
    public function update(Request $request)
    {
        $voucher = Voucher::where('id', $request->id)->first();
        if (!$voucher) {
            return response([
                'message' => 'Không tìm thấy voucher',
            ]);
        }
        $voucher->update([
            'code' => $request->code,
            'image_url' => $request->image_url,
            'description' => $request->description,
            'discount_type' => $request->discount_type, //1: percent, 2: amount
            'discount_percent' => $request->discount_percent,
            'max_discount_amount' => $request->max_discount_amount,
            'min_order_amount' => $request->min_order_amount,
            'expire_at' => $request->expire_at,
            'total_quantity' => $request->total_quantity,
            'used_quantity' => $request->used_quantity,
            'active' => $request->active,
        ]);
        return response([
            'message' => "Sửa voucher thành công",
            'voucher' => $voucher,
        ], 200);
    }
    //xoa voucher
    public function delete(Request $request)
    {
        $voucher = Voucher::where('id', $request->id)->first();
        if (!$voucher) {
            return response([
                'message' => 'Không tìm thấy voucher',
            ]);
        }
        $voucher->update([
            'active' => 0,
        ]);
        return response([
            'message' => "Xóa voucher thành công",
        ], 200);
    }
}
