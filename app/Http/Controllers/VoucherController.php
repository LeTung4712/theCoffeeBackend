<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    //lay danh sach voucher
    public function index()
    {
        $voucherList = Voucher::orderby('id')->get();
        // Chuyển đổi các giá trị số sang dạng số cho từng topping
        foreach ($voucherList as $voucher) {
            $voucher->discount_percent    = (float) $voucher->discount_percent;
            $voucher->max_discount_amount = (float) $voucher->max_discount_amount;
            $voucher->min_order_amount    = (float) $voucher->min_order_amount;
        }
        return $voucherList->isNotEmpty()
        ? response()->json([
            'status'  => true,
            'message' => 'Lấy danh sách voucher thành công',
            'data'    => [
                'vouchers' => $voucherList,
            ],
        ], 200)
        : response()->json([
            'status'  => false,
            'message' => 'Không có voucher',
        ], 404);
    }

    //lay voucher đang hoạt động
    public function indexActive()
    {
        $voucherList = Voucher::where('active', true)->get();
        return $voucherList->isNotEmpty()
        ? response()->json([
            'status'  => true,
            'message' => 'Lấy danh sách voucher thành công',
            'data'    => [
                'vouchers' => $voucherList,
            ],
        ], 200)
        : response()->json([
            'status'  => false,
            'message' => 'Không có voucher',
        ], 404);
    }

    //lay voucher đang hoạt động và sử dụng được cho khách hàng
    public function indexActiveForUser(Request $request)
    {
        $user = $request->user;

        $currentDate = now();

        // Lấy danh sách voucher cơ bản
        $voucherList = Voucher::where('active', true)
            ->where('expire_at', '>', $currentDate)
            ->whereRaw('total_quantity > used_quantity')
            ->get();

        // Lọc thêm các voucher mà user có thể sử dụng
        $voucherList = $voucherList->filter(function ($voucher) use ($user) {
            // Kiểm tra số lần user đã sử dụng voucher này
            $usageCount = VoucherUsage::where('voucher_id', $voucher->id)
                ->where('user_id', $user->id)
                ->count();

            // Chỉ lấy voucher mà user chưa sử dụng hết số lần cho phép
            return $usageCount < $voucher->limit_per_user;
        });

        // Chuyển đổi collection thành array để tránh lỗi khi serialize
        $voucherList = $voucherList->values();

        foreach ($voucherList as $voucher) {
            $voucher->discount_percent    = (float) $voucher->discount_percent;
            $voucher->max_discount_amount = (float) $voucher->max_discount_amount;
            $voucher->min_order_amount    = (float) $voucher->min_order_amount;

            // Lấy số lần người dùng đã sử dụng voucher này
            $usageCount = VoucherUsage::where('voucher_id', $voucher->id)
                ->where('user_id', $user->id)
                ->count();
            //số lần sử dụng voucher còn lại
            $voucher->remaining_usage = $voucher->limit_per_user - $usageCount;
        }

        return $voucherList->isNotEmpty()
        ? response()->json([
            'status'  => true,
            'message' => 'Lấy danh sách voucher thành công',
            'data'    => [
                'vouchers' => $voucherList,
            ],
        ], 200)
        : response()->json([
            'status'  => false,
            'message' => 'Không có voucher khả dụng',
        ], 404);
    }

    //them voucher
    public function create(Request $request)
    {
        $existingVoucher = Voucher::where('code', $request->code)->first();
        if ($existingVoucher) {
            return response()->json([
                'status'  => false,
                'message' => 'Đã có voucher này',
            ], 409);
        }

        try {
            $voucher = Voucher::create([
                'code'                => (string) $request->code,
                'image_url'           => (string) $request->image_url,
                'description'         => (string) $request->description,
                'discount_type'       => (string) $request->discount_type,
                'discount_percent'    => (float) $request->discount_percent,
                'max_discount_amount' => (float) $request->max_discount_amount,
                'min_order_amount'    => (float) $request->min_order_amount,
                'expire_at'           => (string) $request->expire_at,
                'total_quantity'      => (int) $request->total_quantity,
                'used_quantity'       => (int) $request->used_quantity,
                'limit_per_user'      => (int) $request->limit_per_user,
                'active'              => (bool) true,
            ]);
            return response()->json([
                'status'  => true,
                'message' => 'Thêm voucher thành công',
                'data'    => [
                    'voucher' => $voucher,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Thêm voucher thất bại',
            ], 400);
        }
    }
    //sua voucher
    public function update($id, Request $request)
    {
        $voucher = Voucher::where('id', $id)->first();
        if (! $voucher) {
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy voucher',
            ], 404);
        }

        //trừ code của voucher cần sửa với code của voucher đã tồn tại
        $existingVoucher = Voucher::where('code', $request->code)->where('id', '!=', $request->id)->first();
        if ($existingVoucher) {
            return response()->json([
                'status'  => false,
                'message' => 'Đã có voucher này',
            ], 409);
        }

        try {
            $voucher->update([
                'code'                => (string) $request->code,
                'image_url'           => (string) $request->image_url,
                'description'         => (string) $request->description,
                'discount_type'       => (string) $request->discount_type,
                'discount_percent'    => (float) $request->discount_percent,
                'max_discount_amount' => (float) $request->max_discount_amount,
                'min_order_amount'    => (float) $request->min_order_amount,
                'expire_at'           => (string) $request->expire_at,
                'total_quantity'      => (int) $request->total_quantity,
                'used_quantity'       => (int) $request->used_quantity,
                'limit_per_user'      => (int) $request->limit_per_user,
                'active'              => (bool) $request->active,
            ]);
            return response()->json([
                'status'  => true,
                'message' => 'Sửa voucher thành công',
                'data'    => [
                    'voucher' => $voucher,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Sửa voucher thất bại',
            ], 400);
        }
    }

    //xoa voucher
    public function delete($id)
    {
        $voucher = Voucher::where('id', $id)->first();
        if (! $voucher) {
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy voucher',
            ], 404);
        }

        try {
            $voucher->delete();
            return response()->json([
                'status'  => true,
                'message' => 'Xóa voucher thành công',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Xóa voucher thất bại',
            ], 400);
        }
    }

    public function checkVoucher(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code'         => 'required|string',
            'order_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $voucher = Voucher::where('code', $request->code)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->where('quantity', '>', 0)
            ->first();

        if (! $voucher) {
            return response()->json([
                'status'  => false,
                'message' => 'Mã voucher không hợp lệ hoặc đã hết hạn',
            ], 400);
        }

        if ($request->order_amount < $voucher->min_order_amount) {
            return response()->json([
                'status'  => false,
                'message' => 'Đơn hàng chưa đạt giá trị tối thiểu để áp dụng voucher',
            ], 400);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Voucher hợp lệ',
            'data'    => $voucher,
        ]);
    }
}
