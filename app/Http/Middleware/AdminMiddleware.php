<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token   = JWTAuth::parseToken();
            $payload = $token->getPayload();

            if ($payload->get('type') !== 'admin') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Bạn không có quyền truy cập',
                ], 403);
            }

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Token không hợp lệ hoặc hết hạn',
            ], 401);
        }
    }
}
