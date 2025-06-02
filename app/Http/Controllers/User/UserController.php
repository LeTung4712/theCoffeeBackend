<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;

class UserController extends Controller
{
    //update thông tin người dùng bằng id
    public function updateInfo(Request $request)
    {
        $user = User::where('id', $request->id)->first();
        $user->update($request->all());
        return response([
            'message' => 'Update info successfully',
            'userInfo' => $user,
        ], 200);
    }
    //lấy thông tin tất cả người dùng
    public function getAllUser()
    {
        $user = User::all();
        return response([
            'message' => 'Get all user successfully',
            'userInfo' => $user,
        ], 200);
    }

}
