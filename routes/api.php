<?php

use App\Http\Controllers\Admin\AuthAdminController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ToppingController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RecommenderSystem\AprioriController;
use App\Http\Controllers\RecommenderSystem\FP_GrowthController;
use App\Http\Controllers\RecommenderSystem\RecommendationController;
use App\Http\Controllers\User\AddressNoteController;
use App\Http\Controllers\User\AuthUserController;
use App\Http\Controllers\User\UserController;
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

    Route::group(['middleware' => 'admin'], function () {
        Route::group(['prefix' => 'admin'], function () {
            //admin auth api
            Route::post('auth/login', [AuthAdminController::class, 'login']);
        });
        //category api
        Route::group(['prefix' => 'category'], function () {
            Route::get('index', [CategoryController::class, 'index']); // http://localhost:8000/api/v1/admin/category/index
            Route::post('create', [CategoryController::class, 'create']); // vd http://localhost:8000/api/v1/admin/category/create?name=abc&parent_id=2&image_url=abc
            Route::delete('delete', [CategoryController::class, 'delete']); // http://localhost:8000/api/v1/admin/category/delete?id=1
            Route::put('update', [CategoryController::class, 'update']); //http://localhost:8000/api/v1/admin/category/update?id=1&name=abc&parent_id=2
            Route::get('indexByParentId', [CategoryController::class, 'indexByParentId']); // http://localhost:8000/api/v1/admin/category/indexByParentId?parent_id=1
        });
        //product api
        Route::group(['prefix' => 'product'], function () {
            Route::get('index', [ProductController::class, 'index']); // http://localhost:8000/api/v1/admin/product/index
            Route::get('getProductInfo', [ProductController::class, 'getProductInfo']); // http://localhost:8000/api/v1/admin/product/getProductInfo?id=1
            Route::post('create', [ProductController::class, 'create']); // http://localhost:8000/api/v1/admin/product/create?name=abc&category_id=1&price=10000&description=abc&image_url=abc&active=1&price_sale=10000
            Route::put('update', [ProductController::class, 'update']); // http://localhost:8000/api/v1/admin/product/update?id=1&name=abc&category_id=1&price=10000&description=abc&image_url=abc&active=1&price_sale=10000
            Route::delete('delete', [ProductController::class, 'delete']); // http://localhost:8000/api/v1/admin/product/delete?id=1
            Route::get('indexByCategoryId', [ProductController::class, 'indexByCategoryId']); // http://localhost:8000/api/v1/admin/product/indexByCategoryId?category_id=1
        });
        //topping api
        Route::group(['prefix' => 'topping'], function () {
            Route::get('index', [ToppingController::class, 'index']); // http://localhost:8000/api/v1/admin/topping/index
            Route::get('indexActive', [ToppingController::class, 'getActiveToppings']); // http://localhost:8000/api/v1/admin/topping/indexActive
            Route::post('create', [ToppingController::class, 'create']); // http://localhost:8000/api/v1/admin/topping/create?name=abc&price=10000&description=abc&image_url=abc&active=1
            Route::put('update', [ToppingController::class, 'update']); // http://localhost:8000/api/v1/admin/topping/update?id=1&name=abc&price=10000&description=abc&image_url=abc&active=1
            Route::delete('delete', [ToppingController::class, 'delete']); // http://localhost:8000/api/v1/admin/topping/delete?id=1
        });
        //voucher api
        Route::group(['prefix' => 'voucher'], function () {
            Route::get('index', [VoucherController::class, 'index']); // http://localhost:8000/api/v1/admin/voucher/index
            Route::get('indexActive', [VoucherController::class, 'indexActive']); // http://localhost:8000/api/v1/admin/voucher/indexActive
            Route::post('create', [VoucherController::class, 'create']); // http://localhost:8000/api/v1/admin/voucher/create?name=abc&description=abc&image_url=abc&active=1&discount=10000&start_date=2021-01-01&end_date=2021-01-01
            Route::put('update', [VoucherController::class, 'update']); // http://localhost:8000/api/v1/admin/voucher/update?id=1&name=abc&description=abc&image_url=abc&active=1&discount=10000&start_date=2021-01-01&end_date=2021-01-01
            Route::delete('delete', [VoucherController::class, 'delete']); // http://localhost:8000/api/v1/admin/voucher/delete?id=1
        });
        //order api
        Route::group(['prefix' => 'order'], function () {
            Route::post('addOrder', [OrderController::class, 'addOrder']); // http://localhost:8000/api/v1/admin/order/addOrder?user_id=1&user_name=abc&mobile_no=0828035636&address=abc&note=abc&total_price=10000&payment_method=1&products=[{"product_id":1,"product_count":1,"topping_id":1,"topping_count":1,"size":"M","price":10000}]
            Route::put('acceptOrder', [OrderController::class, 'acceptOrder']); // http://localhost:8000/api/v1/admin/order/acceptOrder?order_id=TCH16903883611
            Route::put('paidOrder', [OrderController::class, 'paidOrder']); // http://localhost:8000/api/v1/admin/order/paidOrder?order_id=TCH16903883611
            Route::put('successOrder', [OrderController::class, 'successOrder']); // http://localhost:8000/api/v1/admin/order/successOrder?order_id=TCH16903883611
            Route::put('cancelOrder', [OrderController::class, 'cancelOrder']); // http://localhost:8000/api/v1/admin/order/cancelOrder?order_id=TCH16903883611
            Route::get('getOrderInfo', [OrderController::class, 'getOrderInfo']); // http://
            Route::get('getSuccessOrders', [OrderController::class, 'getSuccessOrders']); // http://localhost:8000/api/v1/admin/order/getSuccessOrder
            Route::get('getUnsuccessOrders', [OrderController::class, 'getUnsuccessOrders']); // http://localhost:8000/api/v1/admin/order/getUnsuccessOrder
            Route::get('getDeliveryOrders', [OrderController::class, 'getDeliveryOrders']); // http://localhost:8000/api/v1/admin/order/getDeliveryOrders
            Route::get('analytics', [OrderController::class, 'getAnalyzeOrders']); // http://localhost:8000/api/v1/admin/order/analytics?timeRange=week
        });

        //api cho recommender system
        Route::group(['prefix' => 'recommenderSystem'], function () {
            // api analyze apriori
            Route::post('analyzeApriori', [AprioriController::class, 'analyzeApriori']); // http://localhost:8000/api/v1/recommenderSystem/analyzeApriori
            Route::get('getTransactions', [AprioriController::class, 'getTransactions']); // http://localhost:8000/api/v1/recommenderSystem/getTransactions
            //api analyze fp-growth
            Route::post('analyzeFpGrowth', [FP_GrowthController::class, 'analyzeFpGrowth']); // http://localhost:8000/api/v1/recommenderSystem/analyzeFpGrowth
           
            //api recommendation
            Route::get('associationRules', [RecommendationController::class, 'getAssociationRules']); // http://localhost:8000/api/v1/recommenderSystem/associationRules
            Route::get('recommendation', [RecommendationController::class, 'getRecommendations']); // http://localhost:8000/api/v1/recommenderSystem/recommendation?cartItems=[1,2,3]
        });
    });

    //api cho userv1/
    Route::group(['prefix' => 'user'], function () {
        //auth api
        Route::post('auth/login', [AuthUserController::class, 'login']); // http://localhost:8000/api/v1/user/auth/login?mobile_no=0828035636
        Route::post('auth/checkOtp', [AuthUserController::class, 'checkOtp']); // http://localhost:8000/api/v1/user/auth/checkOtp?mobile_no=0828035636&otp=123456
        //info api
        Route::put('info/updateInfo', [UserController::class, 'updateInfo']); // http://localhost:8000/api/v1/user/info/updateInfo?id=1&last_name=abc&first_name=abc&gender=nam&birth=1999-01-01&mobile_no=0828035636&email=abc&address=abc
        Route::get('info/getAddressNote', [AddressNoteController::class, 'getAddressNote']); // http://localhost:8000/api/v1/user/info/getAddressNote?id=1
        Route::post('info/createAddressNote', [AddressNoteController::class, 'createAddressNote']);
        Route::put('info/updateAddressNote', [AddressNoteController::class, 'updateAddressNote']);
        Route::delete('info/deleteAddressNote', [AddressNoteController::class, 'deleteAddressNote']);
        Route::get('info/getOrderHistory', [OrderController::class, 'getOrderHistory']); // http://localhost:8000/api/v1/user/order/getOrders?user_id=1
    });

    //api thanh toán
    Route::group(['prefix' => 'payment'], function () {
        Route::post('momo', [PaymentController::class, 'momo_payment']); // http://localhost:8000/api/v1/payment/momo
    });

});
