<?php
namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token   = JWTAuth::setRequest($request)->parseToken();
            $payload = $token->getPayload();

            // Kiểm tra loại token
            if ($payload->get('type') !== 'admin') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Token không hợp lệ cho tài khoản admin',
                ], 403);
            }

            // Lấy thông tin admin từ token
            $adminId = $payload->get('sub');
            $admin   = Admin::find($adminId);
            if (! $admin) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Tài khoản không tồn tại hoặc đã bị vô hiệu hóa',
                ], 401);
            }

            // Thêm thông tin admin vào request
            $request->merge(['admin' => $admin]);
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
