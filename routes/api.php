<?php

use App\Http\Controllers\v1\AuthController;
use Illuminate\Support\Facades\Route;

// مسیرهای عمومی (نیازی به احراز هویت JWT ندارند)
Route::group([
    'middleware' => 'api',
    'prefix' => 'v1'
], function ($router) {
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('auth:api');
    Route::get('upload', [\App\Http\Controllers\v1\UploadController::class, 'index']);
    Route::post('upload', [\App\Http\Controllers\v1\UploadController::class, 'store']);
});
Route::group([
   'prefix' => 'v1'
], function ($router) {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-otp', [AuthController::class, 'verifyOTP']);
    Route::get('users', [\App\Http\Controllers\v1\UserController::class, 'index']);

    Route::post('forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('login_password', [\App\Http\Controllers\v1\AuthController::class, 'loginWithPass']);

});


//    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
//    Route::post('/profile', [AuthController::class, 'profile'])->middleware('auth:api');


//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');

//Route::group(['prefix' => 'v1'], function ($router) {
//    $router->get('users', [\App\Http\Controllers\v1\user\UserController::class, 'index']);
//    $router->get('upload', [\App\Http\Controllers\v1\user\UploadController::class, 'index']);
//    $router->post('upload', [\App\Http\Controllers\v1\user\UploadController::class, 'store']);

//    $router->post('register', [\App\Http\Controllers\v1\AuthController::class, 'register']);
//    $router->post('verify-otp', [\App\Http\Controllers\v1\AuthController::class, 'verifyOTP']);
//    $router->post('resend-otp', [\App\Http\Controllers\v1\AuthController::class, 'resendOTP']);

//    $router->post('login_password',[\App\Http\Controllers\v1\AuthController::class, 'loginWithPass']);
//


