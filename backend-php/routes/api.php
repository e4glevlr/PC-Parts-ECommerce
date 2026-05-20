<?php

use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => ['message' => 'PC Parts E-Commerce API v2.0 - Laravel']);

Route::prefix('v1')->group(function () {
    Route::prefix('users')->group(function () {
        Route::post('login', [UserController::class, 'login']);
        Route::post('register', [UserController::class, 'register']);
        Route::post('refresh-token', [UserController::class, 'refreshToken']);
        Route::post('logout', [UserController::class, 'logout'])->middleware('jwt');
        Route::get('profile', [UserController::class, 'profile'])->middleware('jwt');
        Route::put('profile', [UserController::class, 'updateProfile'])->middleware('jwt');
        Route::put('profile/password', [UserController::class, 'changePassword'])->middleware('jwt');
        Route::post('create', [UserController::class, 'create'])->middleware(['jwt', 'role:ADMIN']);
        Route::get('count', [UserController::class, 'count'])->middleware(['jwt', 'role:ADMIN']);
        Route::get('role/{role}', [UserController::class, 'byRole'])->middleware(['jwt', 'role:ADMIN']);
        Route::get('check/username/{username}', [UserController::class, 'checkUsername']);
        Route::get('check/email/{email}', [UserController::class, 'checkEmail']);
        Route::get('username/{username}', [UserController::class, 'byUsername'])->middleware(['jwt', 'role:ADMIN']);
        Route::get('', [UserController::class, 'index'])->middleware(['jwt', 'role:ADMIN']);
        Route::get('{user}', [UserController::class, 'show'])->middleware(['jwt', 'role:ADMIN']);
        Route::put('{user}', [UserController::class, 'update'])->middleware(['jwt', 'role:ADMIN']);
        Route::delete('{user}', [UserController::class, 'destroy'])->middleware(['jwt', 'role:ADMIN']);
    });

    Route::prefix('products')->group(function () {
        Route::get('management', [ProductController::class, 'management'])->middleware(['jwt', 'role:ADMIN,STAFF']);
        Route::get('count', [ProductController::class, 'count']);
        Route::get('search', [ProductController::class, 'search']);
        Route::get('category/{category}', [ProductController::class, 'byCategory']);
        Route::post('with-image-urls', [ProductController::class, 'createWithImageUrls'])->middleware(['jwt', 'role:ADMIN']);
        Route::get('', [ProductController::class, 'index']);
        Route::post('', [ProductController::class, 'store'])->middleware(['jwt', 'role:ADMIN']);
        Route::get('{product}', [ProductController::class, 'show']);
        Route::put('{product}', [ProductController::class, 'update'])->middleware(['jwt', 'role:ADMIN']);
        Route::delete('{product}', [ProductController::class, 'destroy'])->middleware(['jwt', 'role:ADMIN']);
    });

    Route::prefix('categories')->group(function () {
        Route::get('tree', [CategoryController::class, 'tree']);
        Route::get('parent/{parent}', [CategoryController::class, 'byParent']);
        Route::get('{category}/filters', [CategoryController::class, 'filters']);
        Route::post('{category}/attributes', [CategoryController::class, 'createAttribute'])->middleware(['jwt', 'role:ADMIN']);
        Route::put('{category}/attributes/{attribute}', [CategoryController::class, 'updateAttribute'])->middleware(['jwt', 'role:ADMIN']);
        Route::delete('{category}/attributes/{attribute}', [CategoryController::class, 'deleteAttribute'])->middleware(['jwt', 'role:ADMIN']);
        Route::get('', [CategoryController::class, 'index']);
        Route::post('', [CategoryController::class, 'store'])->middleware(['jwt', 'role:ADMIN']);
        Route::get('{category}', [CategoryController::class, 'show']);
        Route::put('{category}', [CategoryController::class, 'update'])->middleware(['jwt', 'role:ADMIN']);
        Route::delete('{category}', [CategoryController::class, 'destroy'])->middleware(['jwt', 'role:ADMIN']);
    });

    Route::prefix('cart')->middleware(['jwt', 'role:CUSTOMER'])->group(function () {
        Route::get('', [CartController::class, 'show']);
        Route::post('items', [CartController::class, 'addItem']);
        Route::put('items/{item}', [CartController::class, 'updateItem']);
        Route::delete('items/{item}', [CartController::class, 'removeItem']);
        Route::delete('', [CartController::class, 'clear']);
        Route::post('merge', [CartController::class, 'merge']);
        Route::get('user/{user}', [CartController::class, 'showForUser']);
        Route::post('user/{user}/items', [CartController::class, 'addItemForUser']);
        Route::put('user/{user}/items/{item}', [CartController::class, 'updateItemForUser']);
        Route::delete('user/{user}/items/{item}', [CartController::class, 'removeItemForUser']);
        Route::delete('user/{user}', [CartController::class, 'clearForUser']);
    });

    Route::prefix('orders')->group(function () {
        Route::get('my-orders', [OrderController::class, 'myOrders'])->middleware(['jwt', 'role:CUSTOMER']);
        Route::post('by-user', [OrderController::class, 'byUser'])->middleware(['jwt', 'role:ADMIN,STAFF']);
        Route::get('user/{user}', [OrderController::class, 'byUserLegacy'])->middleware('jwt');
        Route::get('code/{code}', [OrderController::class, 'byCode']);
        Route::get('status/{status}', [OrderController::class, 'byStatus'])->middleware(['jwt', 'role:ADMIN,STAFF']);
        Route::get('stats', [OrderController::class, 'stats'])->middleware(['jwt', 'role:ADMIN,STAFF']);
        Route::post('from-cart', [OrderController::class, 'createFromCart'])->middleware(['jwt', 'role:CUSTOMER']);
        Route::get('', [OrderController::class, 'index'])->middleware(['jwt', 'role:ADMIN,STAFF']);
        Route::get('{order}', [OrderController::class, 'show'])->middleware('jwt');
        Route::match(['post', 'put', 'patch'], '{order}/status', [OrderController::class, 'updateStatus'])->middleware(['jwt', 'role:ADMIN,STAFF']);
        Route::match(['post', 'put', 'patch'], '{order}/cancel', [OrderController::class, 'cancel'])->middleware('jwt');
    });

    Route::prefix('comments')->group(function () {
        Route::get('product/{product}', [CommentController::class, 'byProduct']);
        Route::post('product/{product}', [CommentController::class, 'store'])->middleware('jwt');
        Route::get('', [CommentController::class, 'index'])->middleware(['jwt', 'role:ADMIN,STAFF']);
        Route::get('{comment}', [CommentController::class, 'show'])->middleware(['jwt', 'role:ADMIN,STAFF']);
        Route::post('{comment}/reply', [CommentController::class, 'reply'])->middleware('jwt');
        Route::post('{comment}/reply/user/{user}', [CommentController::class, 'replyForUser'])->middleware(['jwt', 'role:ADMIN,STAFF']);
        Route::put('{comment}', [CommentController::class, 'update'])->middleware('jwt');
        Route::delete('{comment}', [CommentController::class, 'destroy'])->middleware('jwt');
    });

    Route::prefix('inventory')->middleware(['jwt', 'role:ADMIN,STAFF'])->group(function () {
        Route::get('products', [InventoryController::class, 'products']);
        Route::get('products/{product}', [InventoryController::class, 'product']);
        Route::get('low-stock', [InventoryController::class, 'lowStock']);
        Route::get('out-of-stock', [InventoryController::class, 'outOfStock']);
        Route::get('need-restock', [InventoryController::class, 'needRestock']);
        Route::post('products/{product}/adjust', [InventoryController::class, 'adjust']);
        Route::put('products/{product}/threshold', [InventoryController::class, 'threshold']);
        Route::post('products/{product}/reserve', [InventoryController::class, 'reserve']);
        Route::post('products/{product}/release', [InventoryController::class, 'release']);
        Route::get('logs', [InventoryController::class, 'logs']);
        Route::get('products/{product}/logs', [InventoryController::class, 'productLogs']);
        Route::get('products/{product}/availability', [InventoryController::class, 'availability']);
        Route::get('{product}', [InventoryController::class, 'productLogs']);
        Route::post('{product}', [InventoryController::class, 'updateStock']);
    });

    Route::prefix('promotions')->group(function () {
        Route::get('active', [PromotionController::class, 'active']);
        Route::get('applicable', [PromotionController::class, 'applicable']);
        Route::get('best', [PromotionController::class, 'best']);
        Route::get('', [PromotionController::class, 'index']);
        Route::post('', [PromotionController::class, 'store'])->middleware(['jwt', 'role:ADMIN']);
        Route::get('{promotion}/calculate-discount', [PromotionController::class, 'calculateDiscount']);
        Route::get('{promotion}', [PromotionController::class, 'show']);
        Route::put('{promotion}', [PromotionController::class, 'update'])->middleware(['jwt', 'role:ADMIN']);
        Route::delete('{promotion}', [PromotionController::class, 'destroy'])->middleware(['jwt', 'role:ADMIN']);
        Route::put('{promotion}/activate', [PromotionController::class, 'activate'])->middleware(['jwt', 'role:ADMIN']);
        Route::put('{promotion}/deactivate', [PromotionController::class, 'deactivate'])->middleware(['jwt', 'role:ADMIN']);
    });
});
