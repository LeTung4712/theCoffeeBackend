<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoongMapService
{
    protected $apiKey;
    protected $baseUrl = 'https://rsapi.goong.io';

    public function __construct()
    {
        $this->apiKey = config('services.goong.api_key');
    }

    public function autocomplete(string $query)
    {
        try {
            // goi api goong để tìm kiếm địa chỉ
            $response = Http::get("{$this->baseUrl}/Place/AutoComplete", [
                'api_key' => $this->apiKey,
                'input'   => $query,
                'location' => '21.007042, 105.842757', // vị trí bk Hà Nội ( ưu tiên tìm kiếm địa chỉ gần đó)
                'limit'   => 5, // giới hạn 5 kết quả 
            ]);

            // nếu thành công thì trả về kết quả    
            if ($response->successful()) {
                return $response->json();
            }

            // nếu thất bại thì log lỗi
            Log::error('Goong API Error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            // nếu thất bại thì trả về lỗi
            return [
                'error'  => 'Không thể kết nối đến Goong API',
                'status' => $response->status(),
            ];

        } catch (\Exception $e) {
            // nếu có lỗi thì log lỗi
            Log::error('Goong API Exception', [
                'message' => $e->getMessage(),
            ]);

            // nếu có lỗi thì trả về lỗi
            return [
                'error'   => 'Có lỗi xảy ra khi gọi Goong API',
                'message' => $e->getMessage(),
            ];
        }
    }
}
