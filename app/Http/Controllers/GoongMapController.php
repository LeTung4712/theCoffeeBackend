<?php
namespace App\Http\Controllers;

use App\Services\GoongMapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class GoongMapController extends Controller
{
    protected $goongMapService;

    public function __construct(GoongMapService $goongMapService)
    {
        $this->goongMapService = $goongMapService;
    }

    public function searchAddress(Request $request)
    {
        // Rate limiting: 60 requests per minute
        $executed = RateLimiter::attempt(
            'goong-api:' . $request->ip(),
            $perMinute = 60,
            function () {
                return true;
            }
        );

        if (! $executed) {
            return response()->json([
                'message' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.',
            ], 429);
        }

        $query = $request->input('query');

        // Cache key dựa trên query
        $cacheKey = 'goong_address:' . md5($query);

        // Kiểm tra cache trước
        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        // Gọi service để lấy dữ liệu từ Goong API
        $result = $this->goongMapService->searchAddress($query);

        // Cache kết quả trong 1 tháng
        Cache::put($cacheKey, $result, now()->addMonth());

        return response()->json($result);
    }
}
