<?php

use App\Http\Controllers\v1\AuthController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'api',
    'prefix' => 'v1'
], function ($router) {
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('auth:api');
    Route::get('userData', [\App\Http\Controllers\v1\ProfileController::class, 'index'])->middleware('auth:api');
    Route::post('update-avatar',[\App\Http\Controllers\v1\ProfileController::class, 'updateAvatar'])->middleware('auth:api');
    Route::post('update-profile',[\App\Http\Controllers\v1\ProfileController::class, 'updateProfile'])->middleware('auth:api');
    Route::post('verify-update' , [\App\Http\Controllers\v1\ProfileController::class, 'verifyUpdatePhoneOtp'])->middleware('auth:api');
    Route::delete('delete-avatar' ,[\App\Http\Controllers\v1\ProfileController::class, 'deleteAvatar'])->middleware('auth:api');
    Route::post('set-password', [\App\Http\Controllers\v1\ProfileController::class, 'setPassword'])->middleware('auth:api');


    Route::get('upload', [\App\Http\Controllers\v1\UploadController::class, 'u']);
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

