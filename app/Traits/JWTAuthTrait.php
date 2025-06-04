<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

trait JWTAuthTrait
{
    /**
     * Lấy thông tin user từ JWT token
     * @return array{user: \App\Models\User|null, payload: \PHPOpenSourceSaver\JWTAuth\Payload|null}
     */
    protected function getJWTAuthInfo(): array
    {
        $token   = JWTAuth::parseToken();
        $payload = $token->getPayload();
        $user    = auth()->user();

        return [
            'user'    => $user,
            'payload' => $payload,
        ];
    }

    /**
     * Kiểm tra xem user có phải là user thông thường không
     * @return bool|JsonResponse
     */
    protected function checkUserAuth()
    {
        $authInfo = $this->getJWTAuthInfo();

        if ($authInfo['payload']->get('type') !== 'user') {
            return response()->json([
                'status'  => false,
                'message' => 'Bạn không có quyền truy cập',
            ], 403);
        }

        return true;
    }

    /**
     * Kiểm tra xem user có phải là admin không
     * @return bool|JsonResponse
     */
    protected function checkAdminAuth()
    {
        $authInfo = $this->getJWTAuthInfo();

        if ($authInfo['payload']->get('type') !== 'admin') {
            return response()->json([
                'status'  => false,
                'message' => 'Bạn không có quyền truy cập',
            ], 403);
        }

        return true;
    }

    /**
     * Kiểm tra xem user hiện tại có phải là chủ sở hữu của resource không
     * @param Model|null $resource Resource cần kiểm tra
     * @param string $message Message lỗi tùy chỉnh
     * @return bool|JsonResponse
     */
    protected function checkResourceOwnership(?Model $resource, string $message = 'Bạn không có quyền thực hiện thao tác này')
    {
        if (! $resource) {
            return response()->json([
                'status'  => false,
                'message' => 'Không tìm thấy dữ liệu',
            ], 404);
        }

        $user = $this->getJWTAuthInfo()['user'];

        if ($resource->user_id !== $user->id) {
            return response()->json([
                'status'  => false,
                'message' => $message,
            ], 403);
        }

        return true;
    }
}
