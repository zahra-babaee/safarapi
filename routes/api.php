<?php

use App\Http\Controllers\v1\ArticleController;
use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\NotificationController;
use App\Http\Controllers\v1\ProfileController;
use App\Http\Controllers\v1\UploadController;
use App\Http\Controllers\v1\UserController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:api'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('userData', [ProfileController::class, 'index']);
    Route::post('update-avatar',[ProfileController::class, 'updateAvatar']);
    Route::post('update-profile',[ProfileController::class, 'updateProfile']);
    Route::post('verify-update' , [ProfileController::class, 'verifyUpdatePhoneOtp']);
    Route::delete('delete-avatar' ,[ProfileController::class, 'deleteAvatar']);
    Route::post('set-password', [ProfileController::class, 'setPassword']);
    Route::post('articles', [ArticleController::class, 'store']);
    Route::put('articles/{article}', [ArticleController::class, 'update']);
    Route::delete('articles/{article}', [ArticleController::class, 'destroy']);
    Route::get('/articles/pending', [ArticleController::class, 'getPendingArticles']);
});
    Route::get('/articles/published', [ArticleController::class, 'getPublishedArticles']);
    Route::get('upload', [UploadController::class, 'u']);
    Route::post('upload', [UploadController::class, 'uploadImage']);

Route::group([
   'prefix' => 'v1'
], function ($router) {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::get('users', [UserController::class, 'index']);

    Route::post('forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('login_password', [AuthController::class, 'loginWithPass']);


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

