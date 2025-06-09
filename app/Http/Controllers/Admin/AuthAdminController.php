<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthAdminController extends Controller
{
    private const REFRESH_TOKEN_EXPIRY_DAYS = 7;
    private const MAX_LOGIN_ATTEMPTS        = 5;
    private const LOCKOUT_MINUTES           = 30;

    public function login(Request $request)
    {
        // Rate limiting cho API login
        $key = 'admin_login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Quá nhiều yêu cầu đăng nhập admin', [
                'ip'          => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'retry_after' => $seconds,
            ]);

            return response()->json([
                'status'      => false,
                'message'     => 'Quá nhiều yêu cầu đăng nhập. Vui lòng thử lại sau ' . ceil($seconds / 60) . ' phút.',
                'retry_after' => $seconds,
            ], 429);
        }
        RateLimiter::hit($key);

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

        $admin = Admin::where('username', $request->username)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Tên đăng nhập hoặc mật khẩu không đúng',
            ], 401);
        }

        // Tạo access token với type là admin
        $token = JWTAuth::claims(['type' => 'admin'])->fromUser($admin);

        // Tạo refresh token
        $refreshToken       = Str::random(64);
        $refreshTokenExpiry = Carbon::now()->addDays(self::REFRESH_TOKEN_EXPIRY_DAYS);

        // Lưu token vào database
        $admin->update([
            'access_token'             => $token,
            'refresh_token'            => Hash::make($refreshToken),
            'refresh_token_expired_at' => $refreshTokenExpiry,
        ]);

        // Tạo cookie chứa refresh token
        $cookie = cookie(
            'admin_refresh_token',
            $refreshToken,
            self::REFRESH_TOKEN_EXPIRY_DAYS * 24 * 60,
            '/',
            true,
            true
        );

        Log::info('Đăng nhập admin thành công', [
            'admin_id' => $admin->id,
            'ip'       => $request->ip(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Đăng nhập thành công',
            'data'    => [
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => config('jwt.ttl') * 60,
            ],
        ])->cookie($cookie);
    }

    public function refreshToken(Request $request)
    {
        try {
            $refreshToken = $request->cookie('admin_refresh_token');
            if (! $refreshToken) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Refresh token không tồn tại',
                ], 401);
            }

            // Lấy tài khoản admin duy nhất
            $admin = Admin::first();

            if (! $admin || ! $admin->refresh_token || ! Hash::check($refreshToken, $admin->refresh_token)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Refresh token không hợp lệ',
                ], 401);
            }

            // Kiểm tra thời gian hết hạn
            $expiredAt = Carbon::parse($admin->refresh_token_expired_at);
            if ($expiredAt->isPast()) {
                // Nếu đã hết hạn thì tạo mới refresh token
                $newRefreshToken = Str::random(64);
                $newExpiredAt    = Carbon::now()->addDays(self::REFRESH_TOKEN_EXPIRY_DAYS);

                $admin->update([
                    'refresh_token'            => Hash::make($newRefreshToken),
                    'refresh_token_expired_at' => $newExpiredAt,
                ]);

                // Tạo cookie mới
                $cookie = cookie(
                    'admin_refresh_token',
                    $newRefreshToken,
                    self::REFRESH_TOKEN_EXPIRY_DAYS * 24 * 60,
                    '/',
                    null,
                    true,
                    true
                );
            } else {
                // Nếu chưa hết hạn thì giữ nguyên refresh token cũ
                $cookie = cookie(
                    'admin_refresh_token',
                    $refreshToken,
                    Carbon::now()->diffInMinutes($expiredAt),
                    '/',
                    null,
                    true,
                    true
                );
            }

            // Tạo access token mới
            $accessToken = JWTAuth::claims(['type' => 'admin'])->fromUser($admin);
            $admin->update(['access_token' => $accessToken]);

            Log::info('Refresh token admin thành công', [
                'admin_id'         => $admin->id,
                'ip'               => $request->ip(),
                'token_expired_at' => $expiredAt,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Làm mới token thành công',
                'data'    => [
                    'access_token' => $accessToken,
                    'token_type'   => 'bearer',
                    'expires_in'   => config('jwt.ttl') * 60,
                ],
            ])->cookie($cookie);

        } catch (\Exception $e) {
            Log::error('Lỗi refresh token admin', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Không thể refresh token',
            ], 401);
        }
    }

    public function logout()
    {
        try {
            $admin = auth('admin')->user();
            if ($admin) {
                $admin->update([
                    'access_token'             => null,
                    'refresh_token'            => null,
                    'refresh_token_expired_at' => null,
                ]);
            }

            $cookie = cookie()->forget('admin_refresh_token');
            auth('admin')->logout();

            Log::info('Đăng xuất admin thành công', [
                'admin_id' => $admin?->id,
                'ip'       => request()->ip(),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Đăng xuất thành công',
            ])->withCookie($cookie);

        } catch (\Exception $e) {
            Log::error('Lỗi đăng xuất admin', [
                'error'    => $e->getMessage(),
                'admin_id' => auth('admin')->id() ?? 'unknown',
                'ip'       => request()->ip(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Có lỗi xảy ra khi đăng xuất',
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
                Log::warning('Đổi mật khẩu admin thất bại - mật khẩu hiện tại không đúng', [
                    'admin_id' => $admin->id,
                    'ip'       => request()->ip(),
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Mật khẩu hiện tại không đúng',
                ], 400);
            }

            $admin->password = Hash::make($request->new_password);
            $admin->save();

            // Đăng xuất sau khi đổi mật khẩu
            auth('admin')->logout();
            $admin->update([
                'refresh_token'            => null,
                'refresh_token_expired_at' => null,
                'last_password_change_at'  => Carbon::now(),
            ]);

            Log::info('Đổi mật khẩu admin thành công', [
                'admin_id' => $admin->id,
                'ip'       => request()->ip(),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Đổi mật khẩu thành công',
            ]);

        } catch (\Exception $e) {
            Log::error('Lỗi đổi mật khẩu admin', [
                'error'    => $e->getMessage(),
                'admin_id' => auth('admin')->id() ?? 'unknown',
                'ip'       => request()->ip(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Có lỗi xảy ra khi đổi mật khẩu',
            ], 500);
        }
    }
}
