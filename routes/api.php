<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ToppingController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\VoucherController;

use App\Http\Controllers\User\Auth\AuthController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\User\AddressNoteController;

use App\Http\Controllers\Payment\PaymentController;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
 */

/*
|--------------------------------------------------------------------------
| API Routes version 1
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'v1'], function () {
    //admin login
    Route::post('admin/login', [LoginController::class, 'login']); // http://localhost:8000/api/v1/admin/login?username=admin@gmail.com&password=123456

    Route::group(['prefix' => 'admin'], function () {
        //category api
        Route::get('category/index', [CategoryController::class, 'index']); // http://localhost:8000/api/v1/admin/category/index
        Route::post('category/create', [CategoryController::class, 'create']); // vd http://localhost:8000/api/v1/admin/category/create?name=abc&parent_id=2&image_url=abc
        Route::delete('category/delete', [CategoryController::class, 'delete']); // http://localhost:8000/api/v1/admin/category/delete?id=1
        Route::put('category/update', [CategoryController::class, 'update']); //http://localhost:8000/api/v1/admin/category/update?id=1&name=abc&parent_id=2
        Route::get('category/indexByParentId', [CategoryController::class, 'indexByParentId']); // http://localhost:8000/api/v1/admin/category/indexByParentId?parent_id=1
        //product api
        Route::get('product/index', [ProductController::class, 'index']); // http://localhost:8000/api/v1/v1/admin/product/index
        Route::get('product/getProductInfo', [ProductController::class, 'getProductInfo']); // http://localhost:8000/api/v1/admin/product/getProductInfo?id=1
        Route::post('product/create', [ProductController::class, 'create']); // http://localhost:8000/api/v1/admin/product/create?name=abc&category_id=1&price=10000&description=abc&image_url=abc&active=1&price_sale=10000
        Route::put('product/update', [ProductController::class, 'update']); // http://localhost:8000/api/v1/admin/product/update?id=1&name=abc&category_id=1&price=10000&description=abc&image_url=abc&active=1&price_sale=10000
        Route::delete('product/delete', [ProductController::class, 'delete']); // http://localhost:8000/api/v1/admin/product/delete?id=1
        Route::get('product/indexByCategoryId', [ProductController::class, 'indexByCategoryId']); // http://localhost:8000/api/v1/admin/product/indexByCategoryId?category_id=1
        //topping api
        Route::get('topping/index', [ToppingController::class, 'index']); // http://localhost:8000/api/v1/admin/topping/index
        Route::post('topping/create', [ToppingController::class, 'create']); // http://localhost:8000/api/v1/admin/topping/create?name=abc&price=10000
        Route::put('topping/update', [ToppingController::class, 'update']); // http://localhost:8000/api/v1/admin/topping/update?id=1&name=abc&price=10000
        Route::delete('topping/delete', [ToppingController::class, 'delete']); // http://localhost:8000/api/v1/admin/topping/delete?id=1
        //voucher api
        Route::get('voucher/index', [VoucherController::class, 'index']); // http://localhost:8000/api/v1/admin/voucher/index
        Route::post('voucher/create', [VoucherController::class, 'create']); // http://localhost:8000/api/v1/admin/voucher/create?code=abc&image_url=abc&description=abc&discount_type=1&discount_percent=10&max_discount_amount=10000&min_order_amount=10000&expire_at=2021-01-01&total_quantity=100&used_quantity=0&active=1
        Route::put('voucher/update', [VoucherController::class, 'update']); // http://localhost:8000/api/v1/admin/voucher/update?id=1&code=abc&image_url=abc&description=abc&discount_type=1&discount_percent=10&max_discount_amount=10000&min_order_amount=10000&expire_at=2021-01-01&total_quantity=100&used_quantity=0&active=1
        Route::delete('voucher/delete', [VoucherController::class, 'delete']); // http://localhost:8000/api/v1/admin/voucher/delete?id=1

        //order api
        Route::post('order/addOrder', [OrderController::class, 'addOrder']); // http://localhost:8000/api/v1/admin/order/addOrder?user_id=1&user_name=abc&mobile_no=0828035636&address=abc&note=abc&total_price=10000&payment_method=1&products=[{"product_id":1,"product_count":1,"topping_id":1,"topping_count":1,"size":"M","price":10000}]
        Route::post('order/getOrders', [OrderController::class, 'getOrders']); // http://localhost:8000/api/v1/admin/order/getOrders?user_id=1
        Route::post('order/acceptOrder', [OrderController::class, 'acceptOrder']); // http://localhost:8000/api/v1/admin/order/acceptOrder?order_id=TCH16903883611
        Route::post('order/paidOrder', [OrderController::class, 'paidOrder']); // http://localhost:8000/api/v1/admin/order/paidOrder?order_id=TCH16903883611
        Route::post('order/cancelOrder', [OrderController::class, 'cancelOrder']); // http://localhost:8000/api/v1/admin/order/cancelOrder?order_id=TCH16903883611
        Route::post('order/getOrderInfo', [OrderController::class, 'getOrderInfo']); // http://127.0.0.1:8000/api/v1/admin/order/getOrderInfo?order_id=TCH16903883611
        Route::get('order/getSuccessOrders', [OrderController::class, 'getSuccessOrders']); // http://localhost:8000/api/v1/admin/order/getSuccessOrder
        Route::get('order/getUnsuccessOrders', [OrderController::class, 'getUnsuccessOrders']); // http://localhost:8000/api/v1/admin/order/getUnsuccessOrder
    });
    
    //api cho userv1/
    Route::group(['prefix' => 'user'], function () {
        //auth api
        Route::post('auth/login', [AuthController::class, 'login']); // http://localhost:8000/api/v1/user/auth/login?mobile_no=0828035636
        Route::post('auth/checkOtp', [AuthController::class, 'checkOtp']); // http://localhost:8000/api/v1/user/auth/checkOtp?mobile_no=0828035636&otp=123456
        Route::put('info/updateInfo', [UserController::class, 'updateInfo']); // http://localhost:8000/api/v1/user/info/updateInfo?id=1&last_name=abc&first_name=abc&gender=nam&birth=1999-01-01&mobile_no=0828035636&email=abc&address=abc
        Route::post('info/getAddressNote', [AddressNoteController::class, 'getAddressNote']); // http://localhost:8000/api/v1/user/info/getAddressNote?id=1
        //api get
        Route::get('category/index', [CategoryController::class, 'index']); // http://localhost:8000/api/v1/user/category/index
        Route::get('product/index', [ProductController::class, 'index']); // http://localhost:8000/api/v1/user/product/index
        Route::get('product/getProductInfo', [ProductController::class, 'getProductInfo']); // http://localhost:8000/api/v1/user/product/getProductInfo?id=1
        Route::get('product/indexByCategoryId', [ProductController::class, 'indexByCategoryId']); // http://localhost:8000/api/v1/user/product/indexByCategoryId?category_id=1
        Route::get('topping/index', [ToppingController::class, 'index']); // http://localhost:8000/api/v1/user/topping/index
        Route::get('order/getOrders', [OrderController::class, 'getOrders']); // http://localhost:8000/api/v1/user/order/getOrders?user_id=1
        Route::get('voucher/index', [VoucherController::class, 'index']); // http://localhost:8000/api/v1/user/voucher/index

    });
    
    //api thanh toán
    Route::group(['prefix' => 'payment'], function () {
        Route::post('momo', [PaymentController::class, 'momo_payment']);   // http://localhost:8000/api/v1/payment/momo
    });
});

