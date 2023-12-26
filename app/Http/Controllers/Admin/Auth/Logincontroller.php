<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Admin;

class Logincontroller extends Controller
{
    
        public function login(Request $request)
        {
            $admin = Admin::where('username', $request->username)->first();
            if ($admin)
                if (Hash::check($request->password, $admin->password)) {//check password hash 
                    return response([
                        'error' => false,
                        'message' => 'Đăng nhập thành công',
                        'admin' => $admin
                    ], 200);
                } else return response([
                    'error' => true,
                    'message' => 'Tài khoản hoặc mật khẩu không đúng',
                ], 500);
        }
    
        public function logout()
        {
            auth()->logout();
            return response([
                'message' => 'Đã đăng xuất'
            ]);
        }
}
