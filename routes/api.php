<?php

use App\Http\Controllers\Admin\AuthAdminController;
use App\Http\Controllers\AnalyzeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GoongMapController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\ToppingController;
use App\Http\Controllers\User\AddressNoteController;
use App\Http\Controllers\User\AuthUserController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\VoucherController;
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
    // Admin routes
    Route::group(['prefix' => 'admin'], function () {
        // Auth routes
        Route::post('auth/login', [AuthAdminController::class, 'login']);
        Route::post('auth/logout', [AuthAdminController::class, 'logout']);
        Route::post('auth/refresh-token', [AuthAdminController::class, 'refreshToken']);

        // Protected routes
        Route::middleware('auth.admin')->group(function () {
            //verification
            Route::get('verification', [AuthUserController::class, 'showVerification']);

            // Categories
            Route::get('categories', [CategoryController::class, 'index']);
            Route::post('categories', [CategoryController::class, 'create']);
            Route::put('categories/{id}', [CategoryController::class, 'update']);
            Route::delete('categories/{id}', [CategoryController::class, 'delete']);
            Route::get('categories/{id}/children', [CategoryController::class, 'indexByParentId']); //http://localhost:8000/api/v1/admin/categories/1/products

            // Products
            Route::get('products', [ProductController::class, 'index']);
            Route::get('products/{id}', [ProductController::class, 'getProductInfo']);
            Route::post('products', [ProductController::class, 'create']);
            Route::put('products/{id}', [ProductController::class, 'update']);
            Route::delete('products/{id}', [ProductController::class, 'delete']);
            Route::get('categories/{categoryId}/products', [ProductController::class, 'indexByCategoryId']);

            // Toppings
            Route::get('toppings', [ToppingController::class, 'index']);
            Route::post('toppings', [ToppingController::class, 'create']);
            Route::put('toppings/{id}', [ToppingController::class, 'update']);
            Route::delete('toppings/{id}', [ToppingController::class, 'delete']);

            // Vouchers
            Route::get('vouchers', [VoucherController::class, 'index']);
            Route::post('vouchers', [VoucherController::class, 'create']);
            Route::put('vouchers/{id}', [VoucherController::class, 'update']);
            Route::delete('vouchers/{id}', [VoucherController::class, 'delete']);

            // Orders - Admin routes
            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{id}', [OrderController::class, 'getOrderInfo']);
            Route::patch('orders/{id}/start-delivery', [OrderController::class, 'startDelivery']);
            //Route::patch('orders/{id}/mark-as-paid', [OrderController::class, 'paidOrder']);
            Route::patch('orders/{id}/complete', [OrderController::class, 'successOrder']);
            Route::patch('orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
            Route::get('orders/status/completed', [OrderController::class, 'getSuccessOrders']);
            Route::get('orders/status/pending-payment', [OrderController::class, 'getPendingPaymentOrders']);
            Route::get('orders/status/pending-delivery', [OrderController::class, 'getPendingDeliveryOrders']);
            Route::get('orders/status/delivering', [OrderController::class, 'getDeliveringOrders']);
            Route::post('analytics', [AnalyzeController::class, 'getAnalyzeOrders']);

            // Recommender
            Route::post('recommender/analyze-shopping', [RecommendationController::class, 'getAnalyzeShoppingBehavior']);
            Route::get('recommender/association-rules', [RecommendationController::class, 'getAssociationRules']);

        });
    });

    // User routes
    Route::group(['prefix' => 'users'], function () {
        // Auth routes
        Route::post('auth/login', [AuthUserController::class, 'login']);
        Route::post('auth/verify-otp', [AuthUserController::class, 'checkOtp']);
        Route::post('auth/refresh-token', [AuthUserController::class, 'refreshToken']);
        Route::post('auth/logout', [AuthUserController::class, 'logout']);

        // Protected routes
        Route::middleware('auth.user')->group(function () {

            // User profile
            Route::get('me', [UserController::class, 'getProfile']);
            Route::put('me', [UserController::class, 'updateProfile']);

            // Addresses
            Route::get('addresses', [AddressNoteController::class, 'show']);
            Route::post('addresses', [AddressNoteController::class, 'create']);
            Route::put('addresses/{id}', [AddressNoteController::class, 'update']);
            Route::delete('addresses/{id}', [AddressNoteController::class, 'delete']);

            // Vouchers
            Route::get('vouchers', [VoucherController::class, 'indexActiveForUser']);

            // Orders
            Route::post('orders', [OrderController::class, 'addOrder']);
            Route::get('orders', [OrderController::class, 'getOrderHistory']);
            Route::patch('orders/{id}/complete', [OrderController::class, 'successOrder']);
            Route::patch('orders/{id}/cancel', [OrderController::class, 'cancelOrder']);
        });
    });

    // Payment routes
    Route::prefix('payments')->group(function () {
        // Protected routes
        Route::middleware('auth.user')->group(function () {
            // Momo
            Route::post('momo', [PaymentController::class, 'momo_payment']);
            // VNPay
            Route::post('vnpay', [PaymentController::class, 'vnpay_payment']);
            // ZaloPay
            Route::post('zalopay', [PaymentController::class, 'zalopay_payment']);
        });
        Route::post('momo/callback', [PaymentController::class, 'momo_callback']);
        Route::post('vnpay/callback', [PaymentController::class, 'vnpay_callback']);
        Route::post('zalopay/callback', [PaymentController::class, 'zalopay_callback']);
    });

    //Public routes
    Route::get('products', [ProductController::class, 'indexActive']);
    Route::get('products/{id}', [ProductController::class, 'getProductInfo']);
    Route::get('categories/{categoryId}/products', [ProductController::class, 'indexByCategoryId']);
    Route::get('categories', [CategoryController::class, 'indexActive']);
    Route::get('toppings', [ToppingController::class, 'indexActive']);
    Route::get('vouchers', [VoucherController::class, 'indexActive']);
    Route::get('recommendations', [RecommendationController::class, 'getRecommendations']);
    // Map services
    Route::get('map/addresses', [GoongMapController::class, 'searchAddress']);
});
