<?php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function getProfile(Request $request)
    {
        $user = $request->user;
        return response()->json([
            'status' => true,
            'data'   => $user,
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user;

        $validator = Validator::make($request->all(), [
            'last_name'     => 'required|string|max:50',
            'first_name'    => 'required|string|max:50',
            'email'         => 'required|email|max:100',
            'date_of_birth' => 'required|date|before:today',
            'gender'        => 'required|in:male,female,other',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Vui lòng nhập đầy đủ thông tin',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $request->only(['last_name', 'first_name', 'email', 'date_of_birth', 'gender']);

        $user->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Cập nhật thông tin thành công',
            'data'    => $user,
        ], 200);
    }

        //lấy thông tin tất cả người dùng
    public function getAllUser()
    {
        $user = User::all();
        return response()->json([
            'status'   => true,
            'message'  => 'Lấy tất cả người dùng thành công',
            'userInfo' => $user,
        ], 200);
    }
}
