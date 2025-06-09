<?php
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateUser
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token   = JWTAuth::setRequest($request)->parseToken();
            $payload = $token->getPayload();

            // Kiểm tra loại token
            if ($payload->get('type') !== 'user') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Token không hợp lệ cho tài khoản user',
                ], 403);
            }

            // Lấy thông tin user từ token
            $userId = $payload->get('sub');
            $user   = User::find($userId);
            
            if (! $user || $user->isLocked()) {
                return response()->json([
                    'status'  => false,
                    'message' => $user && $user->isLocked()
                    ? 'Tài khoản đã bị khóa. Vui lòng thử lại sau ' . $user->locked_until->diffForHumans()
                    : 'Tài khoản không tồn tại',
                ], 401);
            }

            // Thêm thông tin user vào request
            $request->merge(['user' => $user]);
            return $next($request);

        } catch (TokenExpiredException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Token đã hết hạn',
                'code'    => 'token_expired',
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Token không hợp lệ',
                'code'    => 'token_invalid',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Lỗi xác thực',
                'code'    => 'auth_error',
            ], 401);
        }
    }
}
