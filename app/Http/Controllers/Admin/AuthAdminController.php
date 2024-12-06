<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Admin;

class AuthAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin', ['except' => ['login']]); 
        if (!auth('admin')->check()) { //
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'error' => false,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('admin')->factory()->getTTL() * 60 // thời gian hết hạn token
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
}
