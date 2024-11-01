<?php

use App\Http\Controllers\v1\ArticleController;
use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\NotificationController;
use App\Http\Controllers\v1\TicketController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;


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
    Route::put('articles/{id}', [ArticleController::class, 'update'])->middleware('auth:api');
    Route::delete('articles/{article}', [ArticleController::class, 'destroy'])->middleware('auth:api');
    Route::get('user/articles', [ArticleController::class, 'userArticles'])->middleware('auth:api');
    Route::get('/article/{id}', [ArticleController::class, 'show']);
    Route::get('articles-all', [ArticleController::class, 'showArticles']);
    Route::get('/articles/search', [ArticleController::class, 'search']);
    Route::get('/articles/published', [ArticleController::class, 'getPublishedArticles']);
    Route::get('/articles/new', [ArticleController::class, 'NewArticles']);

    Route::post('upload/image', [ArticleController::class, 'uploadImage'])->middleware('auth:api');
//    Route::get('upload', [\App\Http\Controllers\v1\UploadController::class, 'u']);
    Route::post('upload/cover', [\App\Http\Controllers\v1\UploadController::class, 'uploadTemporaryCover'])->middleware('auth:api');
    Route::post('update/cover', [\App\Http\Controllers\v1\UploadController::class, 'updateCover'])->middleware('auth:api');
    Route::post('upload-new-image', [\App\Http\Controllers\v1\UploadController::class, 'uploadImagenew']);

    Route::get('/notifications', [NotificationController::class, 'showUserNotify'])->middleware('auth:api');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->middleware('auth:api');

    Route::post('/tickets', [TicketController::class, 'store'])->middleware('auth:api');
    Route::get('/tickets', [TicketController::class, 'index'])->middleware('auth:api');
    Route::get('/ticket/{id}', [TicketController::class, 'ticket'])->middleware('auth:api');
    Route::get('/ticket/{id}/close', [TicketController::class, 'markAsClose'])->middleware('auth:api');
    Route::post('/tickets/{ticketId}/messages', [TicketController::class, 'storeMessage'])->middleware('auth:api');
    Route::get('/tickets/{ticketId}/messages', [TicketController::class, 'getMessages'])->middleware('auth:api');
});
Route::group([
   'prefix' => 'v1'
], function ($router) {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::get('users', [\App\Http\Controllers\v1\UserController::class, 'index']);

    Route::post('forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('verify-otp-forget', [AuthController::class, 'verifyOtpForReset']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('login_password', [\App\Http\Controllers\v1\AuthController::class, 'loginWithPass']);



    Route::group(['prefix' => 'v1'], function ($router) {
        Route::post('messages',[\App\Http\Controllers\v1\ChatController::class,'message']);

        Route::delete('notifications-old', [NotificationController::class, 'deleteOldNotifications']);
        Route::post('notifications-send', [NotificationController::class, 'sendNotification']);
    });
    Broadcast::routes(['middleware' => ['auth:api']]);


    Route::get('test', function (){
       return response()->json(new \App\Dto\BaseDto(\App\Dto\BaseDtoStatusEnum::ERROR,"خطا"))->setStatusCode(200);
    });
    Route::post('messages',[\App\Http\Controllers\v1\ChatController::class,'message']);
});

