<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin', [
            'except' => ['index', 'indexActive', 'create', 'update', 'delete'],
        ]);
        if (!auth('admin')->check()) { //
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }
    }

    //lay danh sach voucher
    public function index()
    {
        $voucherList = Voucher::orderby('id')->get();
        // Chuyển đổi các giá trị số sang dạng số cho từng topping
        foreach ($voucherList as $voucher) {
            $voucher->max_discount_amount = (float) $voucher->max_discount_amount;
            $voucher->min_order_amount = (float) $voucher->min_order_amount;
        }
        return $voucherList->isNotEmpty()
        ? response()->json(['message' => 'Lấy danh sách voucher thành công', 'vouchers' => $voucherList], 200)
        : response()->json(['message' => 'Không có voucher'], 404);
    }

    //lay voucher đang hoạt động
    public function indexActive()
    {
        $voucherList = Voucher::where('active', true)->orderby('id')->get();
        foreach ($voucherList as $voucher) {
            $voucher->max_discount_amount = (float) $voucher->max_discount_amount;
            $voucher->min_order_amount = (float) $voucher->min_order_amount;
        }
        return $voucherList->isNotEmpty()
        ? response()->json(['message' => 'Lấy danh sách voucher thành công', 'vouchers' => $voucherList], 200)
        : response()->json(['message' => 'Không có voucher'], 404);
    }

    //them voucher
    public function create(Request $request)
    {
        $existingVoucher = Voucher::where('code', $request->code)->first();
        if ($existingVoucher) {
            return response()->json(['message' => 'Đã có voucher này'], 409);
        }

        try {
            $voucher = Voucher::create([
                'code' => (string) $request->code,
                'image_url' => (string) $request->image_url,
                'description' => (string) $request->description,
                'discount_type' => (string) $request->discount_type,
                'discount_percent' => (float) $request->discount_percent,
                'max_discount_amount' => (float) $request->max_discount_amount,
                'min_order_amount' => (float) $request->min_order_amount,
                'expire_at' => (string) $request->expire_at,
                'total_quantity' => (int) $request->total_quantity,
                'used_quantity' => (int) $request->used_quantity,
                'limit_per_user' => (int) $request->limit_per_user,
                'active' => (bool) true,
            ]);
            return response()->json(['message' => 'Thêm voucher thành công', 'voucher' => $voucher], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Thêm voucher thất bại', 'error' => $e->getMessage()], 400);
        }
    }
    //sua voucher
    public function update(Request $request)
    {
        $voucher = Voucher::where('id', $request->id)->first();
        if (!$voucher) {
            return response()->json(['message' => 'Không tìm thấy voucher'], 404);
        }

        //trừ code của voucher cần sửa với code của voucher đã tồn tại
        $existingVoucher = Voucher::where('code', $request->code)->where('id', '!=', $request->id)->first();
        if ($existingVoucher) {
            return response()->json(['message' => 'Đã có voucher này'], 409);
        }

        try {
            $voucher->update([
                'code' => (string) $request->code,
                'image_url' => (string) $request->image_url,
                'description' => (string) $request->description,
                'discount_type' => (string) $request->discount_type,
                'discount_percent' => (float) $request->discount_percent,
                'max_discount_amount' => (float) $request->max_discount_amount,
                'min_order_amount' => (float) $request->min_order_amount,
                'expire_at' => (string) $request->expire_at,
                'total_quantity' => (int) $request->total_quantity,
                'used_quantity' => (int) $request->used_quantity,
                'limit_per_user' => (int) $request->limit_per_user,
                'active' => (bool) $request->active,
            ]);
            return response()->json(['message' => 'Sửa voucher thành công', 'voucher' => $voucher], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Sửa voucher thất bại', 'error' => $e->getMessage()], 400);
        }
    }

    //xoa voucher
    public function delete(Request $request)
    {
        $voucher = Voucher::where('id', $request->id)->first();
        if (!$voucher) {
            return response()->json(['message' => 'Không tìm thấy voucher'], 404);
        }

        try {
            $voucher->update(['active' => 0]);
            return response()->json(['message' => 'Xóa voucher thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Xóa voucher thất bại', 'error' => $e->getMessage()], 400);
        }
    }
}
