<?php

use App\Http\Controllers\v1\ArticleController;
use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\NotificationController;
use Illuminate\Support\Facades\Route;


Route::group([
    'middleware' => 'api',
    'prefix' => 'v1'
], function ($router) {
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::get('userData', [\App\Http\Controllers\v1\ProfileController::class, 'index'])->middleware('auth:api');
    Route::post('update-avatar',[\App\Http\Controllers\v1\ProfileController::class, 'updateAvatar'])->middleware('auth:api');
    Route::post('update-profile',[\App\Http\Controllers\v1\ProfileController::class, 'updateProfile'])->middleware('auth:api');
    Route::post('verify-update' , [\App\Http\Controllers\v1\ProfileController::class, 'verifyUpdatePhoneOtp'])->middleware('auth:api');
    Route::delete('delete-avatar' ,[\App\Http\Controllers\v1\ProfileController::class, 'deleteAvatar'])->middleware('auth:api');
    Route::post('set-password', [\App\Http\Controllers\v1\ProfileController::class, 'setPassword'])->middleware('auth:api');
    Route::post('articles', [ArticleController::class, 'store'])->middleware('auth:api');
    Route::put('articles/{article}', [ArticleController::class, 'update'])->middleware('auth:api');
    Route::delete('articles/{article}', [ArticleController::class, 'destroy'])->middleware('auth:api');
    Route::get('/articles/pending', [ArticleController::class, 'getPendingArticles'])->middleware('auth:api');
});
    Route::get('/articles/published', [ArticleController::class, 'getPublishedArticles']);
    Route::get('upload', [\App\Http\Controllers\v1\UploadController::class, 'u']);
    Route::post('upload-image', [\App\Http\Controllers\v1\UploadController::class, 'uploadImage']);

Route::group([
   'prefix' => 'v1'
], function ($router) {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::get('users', [\App\Http\Controllers\v1\UserController::class, 'index']);

    Route::post('forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('login_password', [\App\Http\Controllers\v1\AuthController::class, 'loginWithPass']);


    Route::middleware('auth:api')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/{id}', [NotificationController::class, 'markAsRead']);
        Route::delete('notifications-old', [NotificationController::class, 'deleteOldNotifications']);
        Route::post('notifications-send', [NotificationController::class, 'sendNotification']);
    });



    Route::get('test', function (){
       return response()->json(new \App\Dto\BaseDto(\App\Dto\BaseDtoStatusEnum::ERROR,"خطا"))->setStatusCode(200);
    });
});

