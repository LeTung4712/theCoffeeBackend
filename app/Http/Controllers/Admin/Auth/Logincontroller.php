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
    public function __construct()
    {
        $this->middleware('auth:admin', ['except' => ['login']]); 
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'error' => false,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('admin')->factory()->getTTL() * 60
        ]);
    }

    public function login()
    {
        $credentials = request(['username', 'password']); // lấy email và password từ request
        //tìm trong bảng admin xem có admin nào có email và password như vậy không
        
        if (! $token = auth('admin')->attempt($credentials)) { 
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function logout()
    {
        auth('admin')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function logina(Request $request)
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
    
        public function logouta()
        {
            auth()->logout();
            return response([
                'message' => 'Đã đăng xuất'
            ]);
        }
}
