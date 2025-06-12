<?php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Twilio\Rest\Client;

class AuthUserController extends Controller
{
    private const REFRESH_TOKEN_EXPIRY_DAYS = 7; // Thời hạn refresh token là 7 ngày

    //đăng nhập
    public function login(Request $request)
    {
        // Rate limiting cho API login
        $key = 'login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response([
                'status'      => false,
                'message'     => 'Quá nhiều yêu cầu đăng nhập. Vui lòng thử lại sau.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }
        RateLimiter::hit($key);

        $validator = validator($request->all(), [
            'mobile_no' => ['required', 'regex:/^0[0-9]{9}$/', 'size:10'],
        ]);

        if ($validator->fails()) {
            return response([
                'status'  => false,
                'message' => 'Số điện thoại không hợp lệ',
            ], 422);
        }

        $user = User::where('mobile_no', $request->mobile_no)->first();

        if ($user && $user->isLocked()) {
            return response([
                'status'  => false,
                'message' => 'Tài khoản đã bị khóa. Vui lòng thử lại sau ' . $user->locked_until->diffForHumans(),
            ], 403);
        }

        if (! $user) {
            $user = User::create([
                'first_name'    => '',
                'last_name'     => 'Guest',
                'mobile_no'     => $request->mobile_no,
                'date_of_birth' => DB::raw('CURRENT_TIMESTAMP'),
                'email'         => '',
                'is_active'     => true,

            ]);
        }

        $sendOtp = $this->sendSmsNotification($user);

        if ($sendOtp) {
            return response([
                'status'  => true,
                'message' => 'OTP đã được gửi thành công',
            ], 200);
        }

        return response([
            'status'  => false,
            'message' => 'Gửi OTP thất bại',
        ], 500);
    }

    //dùng twilio để gửi mã otp
    private function sendSmsNotification($user)
    {
        try {
            // Tạo OTP trước
            $otp = $this->generate($user);

            // Sau đó mới gửi SMS
            $account_sid        = getenv("TWILIO_SID");
            $auth_token         = getenv("TWILIO_TOKEN");
            $twilio_number      = getenv("TWILIO_FROM");
            $twilio_service_sid = getenv("TWILIO_SERVICE_SID");

            if (! $account_sid || ! $auth_token || ! $twilio_number) {
                Log::error('Twilio credentials missing', [
                    'user_id'   => $user->id,
                    'mobile_no' => $user->mobile_no,
                ]);
                return true; // Vẫn trả về true vì OTP đã được tạo
            }

            $client         = new Client($account_sid, $auth_token);
            $receiverNumber = '+84' . substr($user->mobile_no, 1);
            $message        = 'Your OTP to login The Coffee Shop is: ' . $otp;

            $client->messages->create($receiverNumber, [
                'from' => $twilio_number,
                'body' => $message,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Twilio SMS sending failed', [
                'error'     => $e->getMessage(),
                'user_id'   => $user->id,
                'mobile_no' => $user->mobile_no,
            ]);
            return true; // Vẫn trả về true vì OTP đã được tạo
        }
    }

    //
    private function generate($user)
    {
        try {
            $verificationCode = $this->generateOtp($user);
            return $verificationCode->otp;
        } catch (\Exception $e) {
            Log::error('OTP generation failed', [
                'error'   => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            throw $e; // Ném lỗi vì đây là lỗi nghiêm trọng
        }
    }

    //tạo mã otp trong database và có hiệu lực trong 3 phút
    private function generateOtp($user)
    {
        try {
            // Kiểm tra xem có mã otp nào trong database không và lấy mã otp mới nhất
            $verificationCode = VerificationCode::where('user_id', $user->id)
                ->where('expire_at', '>', Carbon::now())
                ->latest()
                ->first();

            if ($verificationCode) {
                return $verificationCode;
            }

            // Nếu không có mã otp hoặc mã otp đó đã hết hiệu lực thì tạo mã otp mới
            return VerificationCode::create([
                'user_id'   => $user->id,
                'otp'       => rand(100000, 999999),
                'expire_at' => Carbon::now()->addMinutes(30),
            ]);
        } catch (\Exception $e) {
            Log::error('OTP creation failed', [
                'error'   => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            throw $e; // Ném lỗi vì đây là lỗi nghiêm trọng
        }
    }

    //kiểm tra mã otp
    public function checkOtp(Request $request)
    {
        //validate dữ liệu
        $validator = validator($request->all(), [
            'mobile_no' => ['required', 'regex:/^0[0-9]{9}$/', 'size:10'],
            'otp'       => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
            ], 422);
        }

        //tìm kiếm người dùng theo số điện thoại
        $user = User::where('mobile_no', $request->mobile_no)->first();
        if (! $user) {
            return response([
                'status'  => false,
                'message' => 'Không tìm thấy người dùng',
            ], 404);
        }

        // Kiểm tra tài khoản có bị khóa không
        if ($user->isLocked()) {
            return response([
                'status'  => false,
                'message' => 'Tài khoản đã bị khóa. Vui lòng thử lại sau ' . $user->locked_until->diffForHumans(),
            ], 403);
        }

        //tìm kiếm mã otp còn hạn sử dụng
        $verificationCode = VerificationCode::where('user_id', $user->id)
            ->where('expire_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (! $verificationCode || $verificationCode->otp !== $request->otp) {
            $user->incrementLoginAttempts();
            return response([
                'status'  => false,
                'message' => 'Mã OTP không hợp lệ hoặc đã hết hạn',
            ], 400);
        }

        // Reset login attempts và tạo token
        $user->resetLoginAttempts();

        // Tạo JWT token với type là user
        $token = JWTAuth::claims(['type' => 'user'])->fromUser($user);

        // Tạo refresh token
        $refreshToken       = Str::random(64);
        $refreshTokenExpiry = Carbon::now()->addDays(self::REFRESH_TOKEN_EXPIRY_DAYS);

        // Lưu refresh token vào database
        $user->update([
            'access_token'             => $token,
            'refresh_token'            => Hash::make($refreshToken), // Hash refresh token trước khi lưu
            'refresh_token_expired_at' => $refreshTokenExpiry,
        ]);

        // Tạo cookie chứa refresh token
        $cookie = cookie(
            'refresh_token',
            $refreshToken,
            self::REFRESH_TOKEN_EXPIRY_DAYS * 24 * 60, // Thời gian sống của cookie (phút)
            '/',                                       // Path
            null,                                      // Domain
            true,                                      // Secure
            true                                       // HttpOnly
        );

        Log::info('Đăng nhập thành công', ['user_id' => $user->id]);

        return response([
            'status'  => true,
            'message' => 'Đăng nhập thành công',
            'data'    => [
                'userInfo'     => $user,
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => config('jwt.ttl') * 60,
            ],
        ])->cookie($cookie);
    }

    //đăng xuất
    public function logout(Request $request)
    {
        try {
            // Lấy user từ request đã được merge bởi middleware
            $user = $request->user;

            if (! $user) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Không tìm thấy thông tin người dùng',
                ], 401);
            }

            // Xóa tokens trong database
            $user->update([
                'access_token'             => null,
                'refresh_token'            => null,
                'refresh_token_expired_at' => null,
            ]);

            // Xóa cookie refresh token
            $cookie = cookie()->forget('refresh_token');

            // Logout khỏi JWT
            auth()->logout();

            return response()->json([
                'status'  => true,
                'message' => 'Đăng xuất thành công',
            ], 200)->withCookie($cookie);

        } catch (\Exception $e) {
            Log::error('Lỗi đăng xuất', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id() ?? 'unknown',
            ]);
            return response()->json([
                'status'  => false,
                'message' => 'Đăng xuất thất bại',
            ], 500);
        }
    }

    //refresh token
    public function refreshToken(Request $request)
    {
        try {
            // Lấy refresh token từ cookie
            $refreshToken = $request->cookie('refresh_token');
            if (! $refreshToken) {
                return response([
                    'status'  => false,
                    'message' => 'Refresh token không tồn tại',
                ], 401);
            }

            // Tìm user có refresh token tương ứng
            $user = User::where('refresh_token_expired_at', '>', Carbon::now())
                ->whereNotNull('refresh_token')
                ->get()
                ->first(function ($user) use ($refreshToken) {
                    return Hash::check($refreshToken, $user->refresh_token);
                });

            if (! $user) {
                return response([
                    'status'  => false,
                    'message' => 'Refresh token không hợp lệ hoặc đã hết hạn',
                ], 401);
            }

            // Kiểm tra thời gian hết hạn
            $expiredAt = Carbon::parse($user->refresh_token_expired_at);
            if ($expiredAt->isPast()) {
                // Nếu đã hết hạn thì tạo mới refresh token
                $newRefreshToken = Str::random(64);
                $newExpiredAt    = Carbon::now()->addDays(self::REFRESH_TOKEN_EXPIRY_DAYS);

                $user->update([
                    'refresh_token'            => Hash::make($newRefreshToken),
                    'refresh_token_expired_at' => $newExpiredAt,
                ]);

                // Tạo cookie mới
                $cookie = cookie(
                    'refresh_token',
                    $newRefreshToken,
                    self::REFRESH_TOKEN_EXPIRY_DAYS * 24 * 60,
                    '/',
                    true,
                    true
                );
            } else {
                // Nếu chưa hết hạn thì giữ nguyên refresh token cũ
                $cookie = cookie(
                    'refresh_token',
                    $refreshToken,
                    Carbon::now()->diffInMinutes($expiredAt),
                    '/',
                    true,
                    true
                );
            }

            // Tạo access token mới
            $token = JWTAuth::claims(['type' => 'user'])->fromUser($user);
            $user->update(['access_token' => $token]);

            Log::info('Refresh token thành công', [
                'user_id'    => $user->id,
                'expires_at' => $expiredAt,
            ]);

            return response([
                'status'  => true,
                'message' => 'Refresh token thành công',
                'data'    => [
                    'access_token' => $token,
                    'token_type'   => 'bearer',
                    'expires_in'   => config('jwt.ttl') * 60,
                ],
            ], 200)->cookie($cookie);

        } catch (\Exception $e) {
            Log::error('Lỗi refresh token', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id() ?? 'unknown',
            ]);
            return response([
                'status'  => false,
                'message' => 'Không thể refresh token',
            ], 401);
        }
    }

    /**
     * Lấy tất cả thông tin mã OTP
     *
     * @return \Illuminate\Http\Response
     */
    public function showVerification()
    {
        try {
            $verificationCodes = VerificationCode::orderBy('created_at', 'desc')->get();

            return response([
                'status'  => true,
                'message' => 'Lấy thông tin OTP thành công',
                'data'    => $verificationCodes,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Lỗi khi lấy thông tin OTP', [
                'error' => $e->getMessage(),
            ]);

            return response([
                'status'  => false,
                'message' => 'Không thể lấy thông tin OTP',
            ], 500);
        }
    }
}
