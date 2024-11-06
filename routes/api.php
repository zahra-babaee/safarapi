<?php

use App\Http\Controllers\v1\ArticleController;
use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\ChatController;
use App\Http\Controllers\v1\NotificationController;
use App\Http\Controllers\v1\ProfileController;
use App\Http\Controllers\v1\TicketController;
use App\Http\Controllers\v1\UploadController;
use App\Http\Controllers\v1\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;


Route::group([
    'middleware' => 'api',
    'prefix' => 'v1'
], function ($router) {
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::get('userData', [ProfileController::class, 'index'])->middleware('auth:api');
    Route::post('update/name', [ProfileController::class, 'updateName'])->middleware('auth:api');
    Route::post('old/phone', [ProfileController::class, 'oldPhone'])->middleware('auth:api');
    Route::post('old/phone/verify', [ProfileController::class, 'verifyOldPhone'])->middleware('auth:api');
    Route::post('update/phone', [ProfileController::class, 'updatePhone'])->middleware('auth:api');
    Route::post('update/phone/verify', [ProfileController::class, 'verifyNewPhoneOtp'])->middleware('auth:api');
    Route::post('update/password', [ProfileController::class, 'updatePassword'])->middleware('auth:api');
    Route::post('set-password', [ProfileController::class, 'setPassword'])->middleware('auth:api');
    Route::post('update/avatar', [ProfileController::class, 'setAvatar'])->middleware('auth:api');
    Route::delete('delete/avatar', [ProfileController::class, 'deleteAvatar'])->middleware('auth:api');

    Route::post('articles', [ArticleController::class, 'store'])->middleware('auth:api');
    Route::patch('edit/article/{id}', [ArticleController::class, 'edit'])->middleware('auth:api');
    Route::post('update/{id}', [ArticleController::class, 'update'])->middleware('auth:api');
    Route::delete('articles/{article}', [ArticleController::class, 'destroy'])->middleware('auth:api');
    Route::get('user/articles', [ArticleController::class, 'userArticles'])->middleware('auth:api');
    Route::get('/article/{id}', [ArticleController::class, 'show']);
    Route::get('articles-all', [ArticleController::class, 'showArticles']);
    Route::get('/articles/search', [ArticleController::class, 'search']);
    Route::get('/articles/published', [ArticleController::class, 'getPublishedArticles']);
    Route::get('/articles/new', [ArticleController::class, 'NewArticles']);

    Route::post('upload/image', [ArticleController::class, 'uploadImage'])->middleware('auth:api');
//    Route::get('upload', [\App\Http\Controllers\v1\UploadController::class, 'u']);
    Route::post('upload/cover', [UploadController::class, 'uploadTemporaryCover'])->middleware('auth:api');
    Route::post('update/cover', [UploadController::class, 'updateCover'])->middleware('auth:api');
    Route::post('upload-new-image', [UploadController::class, 'uploadImagenew']);

    Route::get('/notifications', [NotificationController::class, 'showUserNotify'])->middleware('auth:api');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->middleware('auth:api');

    Route::post('/tickets', [TicketController::class, 'store'])->middleware('auth:api');
    Route::get('/tickets', [TicketController::class, 'index'])->middleware('auth:api');
    Route::get('/ticket/{id}', [TicketController::class, 'ticket'])->middleware('auth:api');
    Route::patch('/ticket/{id}/close', [TicketController::class, 'markAsClose'])->middleware('auth:api');
    Route::post('/tickets/{ticketId}/messages', [TicketController::class, 'storeMessage'])->middleware('auth:api');
    Route::get('/tickets/{ticketId}/messages', [TicketController::class, 'getMessages'])->middleware('auth:api');
});
Route::group([
   'prefix' => 'v1'
], function ($router) {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::get('users', [UserController::class, 'index']);

    Route::post('forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('verify-otp-forget', [AuthController::class, 'verifyOtpForget']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('login_password', [AuthController::class, 'loginWithPass']);



    Route::group(['prefix' => 'v1'], function ($router) {
        Route::post('messages',[ChatController::class,'message']);

        Route::delete('notifications-old', [NotificationController::class, 'deleteOldNotifications']);
        Route::post('notifications-send', [NotificationController::class, 'sendNotification']);
    });
    Broadcast::routes(['middleware' => ['auth:api']]);


    Route::get('test', function (){
       return response()->json(new \App\Dto\BaseDto(\App\Dto\BaseDtoStatusEnum::ERROR,"خطا"))->setStatusCode(200);
    });
    Route::post('messages',[ChatController::class,'message']);
});

