<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Order;
use Symfony\Component\HttpFoundation\Response;

class CheckOrderOwner
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user(); //
        $orderId = $request->route('id'); // lấy {id} từ route
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Không tìm thấy đơn hàng'
            ], 404);
        }

        if ($user->type !== 'admin' && $order->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Bạn không có quyền thay đổi đơn hàng này'
            ], 403);
        }

        return $next($request);
    }
}
