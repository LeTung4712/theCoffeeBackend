<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthAdminController extends Controller
{
    private const REFRESH_TOKEN_EXPIRY_DAYS = 7; // Thời hạn refresh token là 7 ngày
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('username', 'password');
        $admin       = Admin::where('username', $credentials['username'])->first();

        // Kiểm tra credentials
        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Tên đăng nhập hoặc mật khẩu không đúng',
            ], 401);
        }

        // Tạo token bằng JWTAuth facade
        $token = JWTAuth::fromUser($admin);

        // Tạo refresh token
        $refreshToken       = Str::random(64);
        $refreshTokenExpiry = Carbon::now()->addDays(self::REFRESH_TOKEN_EXPIRY_DAYS);

        // Lưu refresh token vào database
        $admin->refresh_token            = $refreshToken;
        $admin->refresh_token_expired_at = $refreshTokenExpiry;
        $admin->save();

        return response()->json([
            'status'  => true,
            'message' => 'Đăng nhập thành công',
            'data'    => [
                'access_token'             => $token,
                'refresh_token'            => $refreshToken,
                'token_type'               => 'bearer',
                'expires_in'               => config('jwt.ttl') * 60,
                'refresh_token_expires_in' => self::REFRESH_TOKEN_EXPIRY_DAYS * 24 * 60 * 60,
                'admin'                    => $admin,
            ],
        ]);
    }

    public function refreshToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Refresh token không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $admin = Admin::where('refresh_token', $request->refresh_token)->first();

        if (! $admin) {
            return response()->json([
                'status'  => false,
                'message' => 'Refresh token không hợp lệ hoặc đã hết hạn',
            ], 401);
        }

        // Tạo token mới
        $token = auth('admin')->login($admin);

        $admin->save();

        return response()->json([
            'status'  => true,
            'message' => 'Làm mới token thành công',
            'data'    => [
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => auth('admin')->factory()->getTTL() * 60,
                'admin'        => $admin,
            ],
        ]);
    }

    public function logout()
    {
        try {
            $admin = auth('admin')->user();
            if ($admin) {
                // Xóa token
                $admin->access_token             = null;
                $admin->refresh_token            = null;
                $admin->refresh_token_expired_at = null;
                $admin->save();
            }

            auth('admin')->logout();

            return response()->json([
                'status'  => true,
                'message' => 'Đăng xuất thành công',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Có lỗi xảy ra khi đăng xuất',
            ], 500);
        }
    }

    public function me()
    {
        try {
            $admin = auth('admin')->user();
            if (! $admin) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Không tìm thấy thông tin admin',
                ], 404);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Lấy thông tin thành công',
                'data'    => $admin,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin',
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string|min:6',
            'new_password'     => 'required|string|min:6|different:current_password',
            'confirm_password' => 'required|string|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $admin = auth('admin')->user();

            if (! Hash::check($request->current_password, $admin->password)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Mật khẩu hiện tại không đúng',
                ], 400);
            }

            $admin->password = Hash::make($request->new_password);
            $admin->save();

            // Đăng xuất sau khi đổi mật khẩu
            auth('admin')->logout();
            $admin->refresh_token = null;
            $admin->save();

            return response()->json([
                'status'  => true,
                'message' => 'Đổi mật khẩu thành công',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Có lỗi xảy ra khi đổi mật khẩu',
            ], 500);
        }
    }
}
