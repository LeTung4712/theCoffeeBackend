<?php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AddressNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AddressNoteController extends Controller
{

    public function create(Request $request)
    {
        // Lấy user từ request đã được merge bởi middleware
        $user = $request->user;

        // Kiểm tra số lượng địa chỉ hiện tại
        $currentAddressCount = $user->addressNotes()->count();
        if ($currentAddressCount >= 4) {
            return response()->json([
                'status'  => false,
                'message' => 'Bạn đã đạt giới hạn tối đa 4 địa chỉ',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'user_name'    => 'required|string|max:100',
            'mobile_no'    => ['required', 'regex:/^0[0-9]{9}$/', 'size:10'],
            'address'      => 'required|string',
            'address_type' => 'required|in:home,office,other',
            'is_default'   => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->is_default) {
            $user->addressNotes()->update(['is_default' => false]);
        }

        $address = $user->addressNotes()->create($request->all());

        return response()->json([
            'status'  => true,
            'message' => 'Thêm địa chỉ thành công',
            'data'    => [
                'address_note' => $address,
            ],
        ], 201);
    }

    public function show(Request $request)
    {
        // Lấy user từ request đã được merge bởi middleware
        $user = $request->user;

        // Lấy danh sách địa chỉ của user
        $addressNote = AddressNote::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($addressNote->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'Không có địa chỉ nào',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => [
                'address_note' => $addressNote,
            ],
        ], 200);

    }

    public function update(Request $request, $id)
    {
        // Lấy user từ request đã được merge bởi middleware
        $user = $request->user;

        // Tìm địa chỉ cần cập nhật
        $addressNote = AddressNote::find($id);

        // Kiểm tra địa chỉ có tồn tại không
        if (! $addressNote) {
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy địa chỉ',
            ], 404);
        }

        // Kiểm tra quyền sở hữu
        if ($addressNote->user_id !== $user->id) {
            return response()->json([
                'status'  => false,
                'message' => 'Không có quyền cập nhật địa chỉ này',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_name'    => 'required|string|max:100',
            'mobile_no'    => ['required', 'regex:/^0[0-9]{9}$/', 'size:10'],
            'address'      => 'required|string',
            'address_type' => 'required|in:home,office,other',
            'is_default'   => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Nếu đặt làm địa chỉ mặc định, cập nhật các địa chỉ khác
            if ($request->is_default) {
                $user->addressNotes()
                    ->where('id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            // Cập nhật địa chỉ
            $addressNote->update($request->all());

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Cập nhật địa chỉ thành công',
                'data'    => $addressNote,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Cập nhật địa chỉ thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request, $id)
    {
        // Lấy user từ request đã được merge bởi middleware
        $user = $request->user;

        // Tìm địa chỉ cần xóa
        $addressNote = AddressNote::find($id);

        // Kiểm tra địa chỉ có tồn tại không
        if (! $addressNote) {
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy địa chỉ',
            ], 404);
        }

        // Kiểm tra quyền sở hữu
        if ($addressNote->user_id !== $user->id) {
            return response()->json([
                'status'  => false,
                'message' => 'Không có quyền xóa địa chỉ này',
            ], 403);
        }

        $addressNote->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Xóa địa chỉ thành công',
        ]);
    }
}
