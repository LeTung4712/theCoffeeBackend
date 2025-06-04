<?php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VerificationCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Kiểm tra tài khoản có bị khóa không
        if ($user && $user->isLocked()) {
            return response([
                'status'  => false,
                'message' => 'Tài khoản đã bị khóa. Vui lòng thử lại sau ' . $user->locked_until->diffForHumans(),
            ], 403);
        }

        if (! $user) {
            $user = User::create([
                'last_name'     => 'Guest',
                'mobile_no'     => $request->mobile_no,
                'date_of_birth' => DB::raw('CURRENT_TIMESTAMP'),
            ]);

            if (! $user) {
                Log::error('Không thể tạo user mới', ['mobile_no' => $request->mobile_no]);
                return response([
                    'status'  => false,
                    'message' => 'Đăng nhập thất bại',
                ], 500);
            }
        }

        $sendOtp = $this->sendSmsNotification($user);

        if ($sendOtp) {
            Log::info('OTP đã được gửi', ['user_id' => $user->id]);
            return response([
                'status'  => true,
                'message' => 'OTP đã được gửi thành công',
                //'sendOtp'     => $sendOtp,
            ], 200);
        } else {
            Log::error('Gửi OTP thất bại', ['user_id' => $user->id]);
            return response([
                'status'  => false,
                'message' => 'Gửi OTP thất bại',
            ], 500);
        }
    }

    //dùng twilio để gửi mã otp
    public function sendSmsNotification($user)
    {
        $account_sid        = getenv("TWILIO_SID");
        $auth_token         = getenv("TWILIO_TOKEN");
        $twilio_number      = getenv("TWILIO_FROM");
        $twilio_service_sid = getenv("TWILIO_SERVICE_SID");
        $client             = new Client($account_sid, $auth_token);
        // Chuyển đổi số điện thoại từ 0 sang +84
        $receiverNumber = '+84' . substr($user->mobile_no, 1);
        $otp            = $this->generate($user);
        $message        = 'Your OTP to login The Coffee Shop is: ' . $otp;

        $result = $client->messages->create($receiverNumber, [
            'from' => $twilio_number,
            'body' => $message,
        ]);
        return $result;
    }
    //
    public function generate($user)
    {
        $verificationCode = $this->generateOtp($user);
        return $verificationCode->otp;
    }

    //tạo mã otp trong database và có hiệu lực trong 3 phút
    public function generateOtp($user)
    {
        // kiểm tra xem có mã otp nào trong database không và lấy mã otp mới nhất
        $verificationCode = VerificationCode::where('user_id', $user->id)->latest()->first();
        // lấy thời gian hiện tại
        $now = Carbon::now();
                                                                                 // nếu có mã otp và mã otp đó vẫn còn hiệu lực thì trả về mã otp đó
        if ($verificationCode && $now->isBefore($verificationCode->expire_at)) { //now()->isBefore : kiểm tra xem thời gian hiện tại có trước thời gian hết hạn của mã otp không

            return $verificationCode;
        }
        // nếu không có mã otp hoặc mã otp đó đã hết hiệu lực thì tạo mã otp mới
        return VerificationCode::create([
            'user_id'   => $user->id,
            'otp'       => rand(100000, 999999),
            'expire_at' => Carbon::now()->addMinutes(30), // thời gian hết hạn = thời gian hiện tại + 3 phút
        ]);
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

        if (! $verificationCode) {
            $user->incrementLoginAttempts();
            return response([
                'status'  => false,
                'message' => 'Mã OTP không hợp lệ',
            ], 400);
        }

        $now = Carbon::now();
        if (strcmp($verificationCode->otp, $request->otp) != 0) {
            $user->incrementLoginAttempts();
            return response([
                'status'  => false,
                'message' => 'Mã OTP không chính xác',
            ], 400);
        }

        if ($now->isAfter($verificationCode->expire_at)) {
            return response([
                'status'  => false,
                'message' => 'Mã OTP đã hết hạn',
            ], 400);
        }

        // Reset login attempts và tạo token
        $user->resetLoginAttempts();

        // Tạo JWT token
        $token = JWTAuth::fromUser($user);

        // Tạo refresh token
        $refreshToken       = Str::random(64);
        $refreshTokenExpiry = Carbon::now()->addDays(self::REFRESH_TOKEN_EXPIRY_DAYS);

        // Lưu tokens vào database
        $user->update([
            'access_token'             => $token,
            'refresh_token'            => $refreshToken,
            'refresh_token_expired_at' => $refreshTokenExpiry,
        ]);

        Log::info('Đăng nhập thành công', ['user_id' => $user->id]);

        return response([
            'status'  => true,
            'message' => 'Đăng nhập thành công',
            'data'    => [
                'userInfo'                 => $user,
                'access_token'             => $token,
                'refresh_token'            => $refreshToken,
                'token_type'               => 'bearer',
                'expires_in'               => config('jwt.ttl') * 60,
                'refresh_token_expires_in' => self::REFRESH_TOKEN_EXPIRY_DAYS * 24 * 60 * 60,
            ],
        ], 200);
    }

    //đăng xuất
    public function logout()
    {
        try {
            // Lấy user từ JWT token hiện tại
            $user = auth()->user();

            if (! $user) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Không tìm thấy thông tin người dùng',
                ], 401);
            }

            // Xóa tokens
            $user->update([
                'access_token'             => null,
                'refresh_token'            => null,
                'refresh_token_expired_at' => null,
            ]);

            // Logout khỏi JWT và trả về JSON response
            auth()->logout();

            return response()->json([
                'status'  => true,
                'message' => 'Đăng xuất thành công',
            ], 200);

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
            $validator = validator($request->all(), [
                'refresh_token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response([
                    'status'  => false,
                    'message' => 'Refresh token không hợp lệ',
                ], 422);
            }

            $user = User::where('refresh_token', $request->refresh_token)
                ->where('refresh_token_expired_at', '>', Carbon::now())
                ->first();

            if (! $user) {
                return response([
                    'status'  => false,
                    'message' => 'Refresh token không hợp lệ hoặc đã hết hạn',
                ], 401);
            }

            // Chỉ tạo JWT token mới
            $token = JWTAuth::fromUser($user);

            // Chỉ cập nhật JWT token mới
            $user->update([
                'access_token' => $token,
            ]);

            Log::info('Refresh token thành công', ['user_id' => $user->id]);

            return response([
                'status'       => true,
                'message'      => 'Refresh token thành công',
                'data'         => [
                    'access_token' => $token,
                    'token_type'   => 'bearer',
                    'expires_in'   => config('jwt.ttl') * 60,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Lỗi refresh token', ['error' => $e->getMessage()]);
            return response([
                'status'  => false,
                'message' => 'Không thể refresh token',
            ], 401);
        }
    }
}
