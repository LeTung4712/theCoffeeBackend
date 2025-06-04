<?php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AddressNote;
use App\Traits\JWTAuthTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AddressNoteController extends Controller
{
    use JWTAuthTrait;
    // Lấy ghi chú địa chỉ
    public function getAddressNote()
    {
        $authCheck = $this->checkUserAuth();
        if ($authCheck !== true) {
            return $authCheck;
        }

        $user = $this->getJWTAuthInfo()['user'];

        $addressNote = AddressNote::where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();
        return $addressNote->isEmpty()
        ? response()->json([
            'status'  => false,
            'message' => 'Không có ghi chú địa chỉ',
        ], 404)
        : response()->json([
            'status'  => true,
            'message' => 'Lấy ghi chú địa chỉ thành công',
            'data'    => [
                'address_note' => $addressNote,
            ],
        ], 200);
    }

    // Thêm ghi chú địa chỉ
    public function createAddressNote(Request $request)
    {
        $authCheck = $this->checkUserAuth();
        if ($authCheck !== true) {
            return $authCheck;
        }

        $user = $this->getJWTAuthInfo()['user'];

        // Validate dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'user_name'    => 'required|string|max:100',
            'mobile_no'    => ['required', 'regex:/^0[0-9]{9}$/', 'size:10'],
            'address'      => 'required|string',
            'address_type' => 'required|in:home,office,other',
            'is_default'   => 'required|boolean',

        ]);

        if ($validator->fails()) {
            \Log::error('Lỗi khi thêm địa chỉ: ' . $validator->errors());
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
            ], 422);
        }

        // Giới hạn số lượng ghi chú địa chỉ của user là 4
        if (AddressNote::where('user_id', $user->id)->count() >= 4) {
            \Log::error('Lỗi khi thêm địa chỉ: ' . 'Số lượng ghi chú địa chỉ của bạn đã đạt giới hạn là 4');
            return response()->json([
                'status'  => false,
                'message' => 'Số lượng ghi chú địa chỉ của bạn đã đạt giới hạn là 4',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Tạo địa chỉ mới với user_id từ token
            $addressNote          = new AddressNote();
            $addressNote->user_id = $user->id;
            $addressNote->fill($request->only([
                'user_name', 'mobile_no', 'address', 'place_id', 'address_type',
                'is_default',
            ]));
            $addressNote->save();

            // Nếu is_default = 1 thì cập nhật lại các địa chỉ còn lại của user về is_default = 0
            if ($request->is_default) {
                AddressNote::where('user_id', $user->id)
                    ->where('id', '!=', $addressNote->id)
                    ->update(['is_default' => 0]);
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Thêm ghi chú địa chỉ thành công',
                'data'    => [
                    'address_note' => $addressNote,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Lỗi khi thêm địa chỉ: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Thêm ghi chú địa chỉ không thành công',
            ], 400);
        }
    }

    // Cập nhật ghi chú địa chỉ
    public function updateAddressNote($id, Request $request)
    {
        $authCheck = $this->checkUserAuth();
        if ($authCheck !== true) {
            return $authCheck;
        }

        $user = $this->getJWTAuthInfo()['user'];

        // Kiểm tra xem địa chỉ có tồn tại không
        $addressNote = AddressNote::find($id);
        if (! $addressNote) {
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy ghi chú địa chỉ',
            ], 404);
        }

        // Kiểm tra quyền sở hữu
        $ownershipCheck = $this->checkResourceOwnership(
            $addressNote,
            'Bạn không có quyền chỉnh sửa địa chỉ này'
        );
        if ($ownershipCheck !== true) {
            return $ownershipCheck;
        }

        // Validate dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'user_name'    => 'required|string|max:100',
            'mobile_no'    => ['required', 'regex:/^0[0-9]{9}$/', 'size:10'],
            'address'      => 'required|string',
            'address_type' => 'required|in:home,office,other',
            'is_default'   => 'required|boolean',

        ]);

        if ($validator->fails()) {
            \Log::error('Lỗi khi cập nhật địa chỉ: ' . $validator->errors());
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
            ], 422);
        }

        $addressNote->fill($request->only([
            'user_name', 'mobile_no', 'address', 'place_id', 'address_type', 'is_default',
        ]));

        try {
            DB::beginTransaction();
            $addressNote->save();
            // Nếu is_default = 1 thì cập nhật lại các địa chỉ còn lại của user về is_default = 0
            if ($request->is_default == 1) {
                AddressNote::where('user_id', $user->id)
                    ->where('id', '!=', $addressNote->id)
                    ->update(['is_default' => 0]);
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Cập nhật ghi chú địa chỉ thành công',
                'data'    => [
                    'address_note' => $addressNote,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Lỗi khi cập nhật địa chỉ: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Cập nhật ghi chú địa chỉ không thành công',
            ], 400);
        }
    }

    // Xóa ghi chú địa chỉ
    public function deleteAddressNote($id)
    {
        $authCheck = $this->checkUserAuth();
        if ($authCheck !== true) {
            return $authCheck;
        }

        $user        = $this->getJWTAuthInfo()['user'];
        $addressNote = AddressNote::find($id);

        // Kiểm tra quyền sở hữu (bao gồm cả kiểm tra tồn tại)
        $ownershipCheck = $this->checkResourceOwnership(
            $addressNote,
            'Bạn không có quyền xóa địa chỉ này'
        );
        if ($ownershipCheck !== true) {
            return $ownershipCheck;
        }

        try {
            DB::beginTransaction();

            $addressNote->delete();
            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Xóa ghi chú địa chỉ thành công',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Lỗi khi xóa địa chỉ: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Xóa ghi chú địa chỉ không thành công',
            ], 400);
        }
    }
}
