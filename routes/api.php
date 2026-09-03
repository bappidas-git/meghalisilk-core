<?php

use App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Api\V1\Auth\AdminAuthController;
use App\Http\Controllers\Api\V1\Auth\CustomerAuthController;
use App\Http\Controllers\Api\V1\Storefront;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Meghali's Silk API — /api/v1 (see api-creation-guide.md §7 / §13)
|--------------------------------------------------------------------------
|
| Auth scopes:  P = public, C = customer Bearer token (ability "customer"),
|               A = admin Bearer token (ability "admin", URL contains /admin/).
| Every success body is { success: true, data }, every error { message, errors? }.
|
*/

Route::prefix('v1')->group(function () {

    /* ---------------------------------------------------------------- Public */

    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/login', [CustomerAuthController::class, 'login']);
        Route::post('auth/register', [CustomerAuthController::class, 'register']);
        Route::post('admin/auth/login', [AdminAuthController::class, 'login']);
    });

    Route::get('products', [Storefront\ProductController::class, 'index']);
    Route::get('products/featured', [Storefront\ProductController::class, 'featured']);
    Route::get('products/trending', [Storefront\ProductController::class, 'trending']);
    Route::get('products/slug/{slug}', [Storefront\ProductController::class, 'showBySlug']);
    Route::get('products/category/{categoryId}', [Storefront\ProductController::class, 'byCategory'])->whereNumber('categoryId');
    Route::get('products/{productId}/reviews', [Storefront\ProductController::class, 'reviews'])->whereNumber('productId');
    Route::get('products/{id}', [Storefront\ProductController::class, 'show'])->whereNumber('id');

    Route::get('categories', [Storefront\CategoryController::class, 'index']);
    Route::get('categories/slug/{slug}', [Storefront\CategoryController::class, 'showBySlug']);
    Route::get('categories/{id}', [Storefront\CategoryController::class, 'show'])->whereNumber('id');

    Route::get('banners', [Storefront\BannerController::class, 'index']);
    Route::get('hero/config', [Storefront\HeroController::class, 'config']);
    Route::get('coupons', [Storefront\CouponController::class, 'index']);
    Route::get('shipping/methods', [Storefront\ShippingController::class, 'methods']);
    Route::get('settings', [Storefront\SettingsController::class, 'show']);
    Route::get('faqs', [Storefront\FaqController::class, 'index']);
    Route::get('deals/config', [Storefront\DealsController::class, 'config']);

    Route::middleware('throttle:forms')->group(function () {
        Route::post('coupons/validate', [Storefront\CouponController::class, 'validateCode']);
        Route::post('leads/contact', [Storefront\LeadController::class, 'contact']);
        Route::post('leads/newsletter', [Storefront\LeadController::class, 'newsletter']);
    });

    /* -------------------------------------------------------------- Customer */

    Route::middleware(['auth:sanctum', 'ability:customer', 'account.active'])->group(function () {
        Route::post('auth/logout', [CustomerAuthController::class, 'logout']);
        Route::get('auth/user', [CustomerAuthController::class, 'user']);
        Route::put('auth/user', [CustomerAuthController::class, 'updateUser']);
        Route::put('auth/password', [CustomerAuthController::class, 'changePassword']);

        Route::post('products/{productId}/reviews', [Storefront\ReviewController::class, 'store'])->whereNumber('productId');
        Route::get('reviews/mine', [Storefront\ReviewController::class, 'mine']);

        Route::get('cart', [Storefront\CartController::class, 'index']);
        Route::post('cart', [Storefront\CartController::class, 'store']);
        Route::delete('cart', [Storefront\CartController::class, 'clear']);
        Route::patch('cart/{id}', [Storefront\CartController::class, 'update'])->whereNumber('id');
        Route::delete('cart/{id}', [Storefront\CartController::class, 'destroy'])->whereNumber('id');

        Route::post('orders', [Storefront\OrderController::class, 'store']);
        Route::get('orders', [Storefront\OrderController::class, 'index']);
        Route::get('orders/number/{orderNumber}', [Storefront\OrderController::class, 'showByNumber']);
        Route::get('orders/{id}', [Storefront\OrderController::class, 'show'])->whereNumber('id');
        Route::post('orders/{id}/cancel', [Storefront\OrderController::class, 'cancel'])->whereNumber('id');

        Route::get('wallet/balance', [Storefront\WalletController::class, 'balance']);
        Route::get('wallet/transactions', [Storefront\WalletController::class, 'transactions']);

        Route::post('returns', [Storefront\ReturnController::class, 'store']);
        Route::get('returns', [Storefront\ReturnController::class, 'index']);
        Route::get('returns/{id}', [Storefront\ReturnController::class, 'show'])->whereNumber('id');

        Route::get('wishlist', [Storefront\WishlistController::class, 'index']);
        Route::post('wishlist', [Storefront\WishlistController::class, 'store']);
        Route::delete('wishlist/{id}', [Storefront\WishlistController::class, 'destroy'])->whereNumber('id');
    });

    /* ----------------------------------------------------------------- Admin */

    Route::prefix('admin')->middleware(['auth:sanctum', 'ability:admin', 'account.active'])->group(function () {
        Route::post('auth/logout', [AdminAuthController::class, 'logout']);

        Route::get('dashboard/stats', [Admin\DashboardController::class, 'stats']);

        Route::get('products', [Admin\ProductController::class, 'index']);
        Route::post('products', [Admin\ProductController::class, 'store']);
        Route::get('products/{id}', [Admin\ProductController::class, 'show'])->whereNumber('id');
        Route::put('products/{id}', [Admin\ProductController::class, 'update'])->whereNumber('id');
        Route::delete('products/{id}', [Admin\ProductController::class, 'destroy'])->whereNumber('id');

        Route::get('categories', [Admin\CategoryController::class, 'index']);
        Route::post('categories', [Admin\CategoryController::class, 'store']);
        Route::put('categories/{id}', [Admin\CategoryController::class, 'update'])->whereNumber('id');
        Route::delete('categories/{id}', [Admin\CategoryController::class, 'destroy'])->whereNumber('id');

        Route::get('orders', [Admin\OrderController::class, 'index']);
        Route::get('orders/{id}', [Admin\OrderController::class, 'show'])->whereNumber('id');
        Route::patch('orders/{id}', [Admin\OrderController::class, 'update'])->whereNumber('id');
        Route::post('orders/{id}/cancel', [Admin\OrderController::class, 'cancel'])->whereNumber('id');
        Route::post('orders/{id}/refund/initiate', [Admin\OrderRefundController::class, 'initiate'])->whereNumber('id');
        Route::post('orders/{id}/refund/complete', [Admin\OrderRefundController::class, 'complete'])->whereNumber('id');
        Route::post('orders/{id}/refund/fail', [Admin\OrderRefundController::class, 'fail'])->whereNumber('id');

        Route::get('returns', [Admin\ReturnController::class, 'index']);
        Route::post('returns', [Admin\ReturnController::class, 'store']);
        Route::get('returns/{id}', [Admin\ReturnController::class, 'show'])->whereNumber('id');
        Route::patch('returns/{id}', [Admin\ReturnController::class, 'update'])->whereNumber('id');

        Route::get('payments', [Admin\PaymentController::class, 'index']);
        Route::get('payments/{id}', [Admin\PaymentController::class, 'show'])->whereNumber('id');
        Route::post('payments/{id}/refund', [Admin\PaymentController::class, 'refund'])->whereNumber('id');
        Route::get('refunds', [Admin\RefundController::class, 'index']);

        Route::get('shipping-methods', [Admin\ShippingMethodController::class, 'index']);
        Route::post('shipping-methods', [Admin\ShippingMethodController::class, 'store']);
        Route::put('shipping-methods/{id}', [Admin\ShippingMethodController::class, 'update'])->whereNumber('id');
        Route::delete('shipping-methods/{id}', [Admin\ShippingMethodController::class, 'destroy'])->whereNumber('id');
        Route::post('shipping/shiprocket/order', [Admin\ShiprocketController::class, 'createOrder']);
        Route::get('shipping/shiprocket/track/{trackingNumber}', [Admin\ShiprocketController::class, 'track']);

        Route::get('coupons', [Admin\CouponController::class, 'index']);
        Route::post('coupons', [Admin\CouponController::class, 'store']);
        Route::put('coupons/{id}', [Admin\CouponController::class, 'update'])->whereNumber('id');
        Route::delete('coupons/{id}', [Admin\CouponController::class, 'destroy'])->whereNumber('id');

        Route::get('reviews', [Admin\ReviewController::class, 'index']);
        Route::post('reviews', [Admin\ReviewController::class, 'store']);
        Route::patch('reviews/{id}', [Admin\ReviewController::class, 'update'])->whereNumber('id');
        Route::delete('reviews/{id}', [Admin\ReviewController::class, 'destroy'])->whereNumber('id');

        Route::get('users', [Admin\UserController::class, 'index']);
        Route::get('users/{id}', [Admin\UserController::class, 'show'])->whereNumber('id');
        Route::patch('users/{id}', [Admin\UserController::class, 'update'])->whereNumber('id');

        Route::get('leads', [Admin\LeadController::class, 'index']);
        Route::get('leads/{id}', [Admin\LeadController::class, 'show'])->whereNumber('id');
        Route::patch('leads/{id}', [Admin\LeadController::class, 'update'])->whereNumber('id');
        Route::delete('leads/{id}', [Admin\LeadController::class, 'destroy'])->whereNumber('id');

        Route::get('settings', [Admin\SettingsController::class, 'index']);
        Route::patch('settings/{section}', [Admin\SettingsController::class, 'update']);

        Route::get('deals/config', [Admin\DealsConfigController::class, 'show']);
        Route::put('deals/config', [Admin\DealsConfigController::class, 'update']);

        Route::get('hero/config', [Admin\HeroConfigController::class, 'show']);
        Route::put('hero/config', [Admin\HeroConfigController::class, 'update']);

        Route::get('banners', [Admin\BannerController::class, 'index']);
        Route::post('banners', [Admin\BannerController::class, 'store']);
        Route::put('banners/reorder', [Admin\BannerController::class, 'reorder']);
        Route::put('banners/{id}', [Admin\BannerController::class, 'update'])->whereNumber('id');
        Route::delete('banners/{id}', [Admin\BannerController::class, 'destroy'])->whereNumber('id');

        Route::get('faqs', [Admin\FaqController::class, 'index']);
        Route::post('faqs', [Admin\FaqController::class, 'store']);
        Route::put('faqs/reorder', [Admin\FaqController::class, 'reorder']);
        Route::put('faqs/{id}', [Admin\FaqController::class, 'update'])->whereNumber('id');
        Route::delete('faqs/{id}', [Admin\FaqController::class, 'destroy'])->whereNumber('id');
    });
});
