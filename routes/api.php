<?php

use App\Http\Controllers\v1\AuthController;
use Illuminate\Support\Facades\Route;

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
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::get('users', [\App\Http\Controllers\v1\UserController::class, 'index']);

    Route::post('forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('login_password', [\App\Http\Controllers\v1\AuthController::class, 'loginWithPass']);

});

