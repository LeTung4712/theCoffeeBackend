<?php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\JWTAuthTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    use JWTAuthTrait;
    //update thông tin người dùng bằng id
    public function updateInfo(Request $request)
    {
        $authCheck = $this->checkUserAuth();
        if ($authCheck !== true) {
            return $authCheck;
        }

        $user = $this->getJWTAuthInfo()['user'];

        // Validate dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'last_name'     => 'required|string|max:50',
            'first_name'    => 'required|string|max:50',
            'email'         => 'required|email|max:100' . $user->id,
            'date_of_birth' => 'required|date|before:today',
            'gender'        => 'required|in:male,female,other',
        ]);

        if ($validator->fails()) {
            \Log::error('Lỗi khi cập nhật thông tin user #' . auth()->id() . ': ' . $validator->errors());
            return response()->json([
                'status'  => false,
                'message' => 'Dữ liệu không hợp lệ',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = User::findOrFail(auth()->id());
            $user->update([
                'last_name'     => $request->last_name,
                'first_name'    => $request->first_name,
                'email'         => $request->email,
                'date_of_birth' => $request->date_of_birth,
                'gender'        => $request->gender,
            ]);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Cập nhật thông tin thành công',
                'data'    => [
                    'user' => $user->only([
                        'id', 'last_name', 'first_name', 'email',
                        'date_of_birth', 'gender', 'avatar', 'token',
                    ]),
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Lỗi khi cập nhật thông tin user #' . auth()->id() . ': ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Cập nhật thông tin thất bại',
            ], 400);
        }
    }
    //lấy thông tin tất cả người dùng
    public function getAllUser()
    {
        $user = User::all();
        return response()->json([
            'status'   => true,
            'message'  => 'Lấy tất cả người dùng thành công',
            'userInfo' => $user,
        ], 200);
    }

}
