<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AddressNote;
use Illuminate\Http\Request;

class AddressNoteController extends Controller
{
    // Lấy ghi chú địa chỉ
    public function getAddressNote(Request $request){
        $addressNote = AddressNote::where('user_id', $request->user_id)
                                    ->orderByDesc('id')
                                    ->get();
        return $addressNote -> isEmpty() 
            ? response () -> json(['message' => 'Không có ghi chú địa chỉ'], 404)
            : response () -> json(['message' => 'Lấy ghi chú địa chỉ thành công', 'address_note' => $addressNote], 200);
    }

    // Thêm ghi chú địa chỉ
    public function createAddressNote(Request $request){
        // Giới hạn số lượng ghi chú địa chỉ của user là 4
        if (AddressNote::where('user_id', $request->user_id)->count() >= 4) {
            return response()->json(['message' => 'Số lượng ghi chú địa chỉ của bạn đã đạt giới hạn'], 400);
        }

        $addressNote = new AddressNote($request->only([
            'user_id', 'user_name', 'mobile_no', 'address', 'address_type', 'is_default', 'province_code', 'district_code', 'ward_code'
        ]));

        try {
            $addressNote->save();

            // Nếu is_default = 1 thì cập nhật lại các địa chỉ còn lại của user về is_default = 0
            if ($request->is_default == 1) {
                AddressNote::where('user_id', $request->user_id)
                    ->where('id', '!=', $addressNote->id)
                    ->update(['is_default' => 0]);
            }

            return response()->json(['message' => 'Thêm ghi chú địa chỉ thành công', 'address_note' => $addressNote], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Thêm ghi chú địa chỉ không thành công', 'error' => $e->getMessage()], 400);
        }
    }

    // Cập nhật ghi chú địa chỉ
    public function updateAddressNote (Request $request){
        $addressNote = AddressNote::find($request->address_id);
        if (!$addressNote) {
            return response()->json(['message' => 'Không tìm thấy ghi chú địa chỉ'], 404);
        }

        $addressNote->fill($request->only([
            'user_name', 'mobile_no', 'address', 'address_type', 'is_default', 'province_code', 'district_code', 'ward_code'
        ]));

        try {
            $addressNote->save();

            // Nếu is_default = 1 thì cập nhật lại các địa chỉ còn lại của user về is_default = 0
            if ($request->is_default == 1) {
                AddressNote::where('user_id', $request->user_id)
                    ->where('id', '!=', $addressNote->id)
                    ->update(['is_default' => 0]);
            }

            return response()->json(['message' => 'Cập nhật ghi chú địa chỉ thành công', 'address_note' => $addressNote], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Cập nhật ghi chú địa chỉ không thành công', 'error' => $e->getMessage()], 400);
        }
    }

    // Xóa ghi chú địa chỉ
    public function deleteAddressNote (Request $request){
        $addressNote = AddressNote::where('id', $request->address_id)->first();
        if ($addressNote == null) {
            return response(['message' => 'Không tìm thấy ghi chú địa chỉ'], 404);
        }
        try {
            $addressNote->delete();
            return response(['message' => 'Xóa ghi chú địa chỉ thành công'], 200);
        } catch (\Exception $e) {
            return response(['message' => 'Xóa ghi chú địa chỉ không thành công' . $e->getMessage()], 400);
        }
    }
}
