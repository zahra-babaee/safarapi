<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/notifications",
     *     summary="دریافت اعلانات کاربر",
     *     description="این متد تمام اعلانات مربوط به کاربر را نمایش می‌دهد. با استفاده از پارامتر `is_read` می‌توان اعلانات را بر اساس خوانده شده یا نشده بودن فیلتر کرد.",
     *     tags={"اعلانات"},
     *     @OA\Parameter(
     *         name="is_read",
     *         in="query",
     *         description="فیلتر وضعیت خوانده شده (0=خوانده نشده، 1=خوانده شده)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="لیست اعلانات",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="خوش آمدید"),
     *                     @OA\Property(property="description", type="string", example="به سایت ما خوش آمدید"),
     *                     @OA\Property(property="type", type="string", example="wellcome"),
     *                     @OA\Property(property="is_read", type="boolean", example=false)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function showUserNotify(Request $request)
    {
        $user = $request->user();
        $isRead = $request->query('is_read'); // فیلتر وضعیت خوانده‌شدن

        $query = UserNotification::with('notification')
            ->where('user_id', $user->id);

        if (!is_null($isRead)) {
            $query->where('is_read', $isRead);
        }

        $userNotifications = $query->get();

        $notifications = $userNotifications->map(function ($userNotification) {
            return [
                'id' => $userNotification->notification->id,
                'title' => $userNotification->notification->title,
                'description' => $userNotification->notification->description,
                'type' => $userNotification->notification->type,
                'is_read' => $userNotification->is_read
            ];
        });

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK,
            data:[
                $notifications,
            ]

        ),200);
    }
    /**
     * @OA\patch(
     *     path="/api/v1//notifications/{id}/read",
     *     summary="علامت‌گذاری اعلان به عنوان خوانده شده",
     *     description="این متد یک اعلان خاص را برای کاربر به عنوان خوانده شده علامت‌گذاری می‌کند.",
     *     tags={"اعلانات"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="شناسه اعلان",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="اعلان به عنوان خوانده شده علامت‌گذاری شد",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="Notification marked as read")
     *         )
     *     ),
     *     @OA\Response(response=404, description="اعلان یافت نشد"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function markAsRead($id, Request $request)
    {
        $userNotification = UserNotification::query()->where('user_id', $request->user()->id)
            ->where('notification_id', $id)
            ->firstOrFail();

        $userNotification->is_read = true;
        $userNotification->save();

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK,'وضعیت اعلان به خوانده شده تغییر کرد'),200);
    }
    public function sendPublicNotifications()
    {
        // به‌دست آوردن تمام کاربران
        $users = User::all(); // یا به دلخواه خود، می‌توانید کاربرانی که می‌خواهید را فیلتر کنید.

        // آیدی‌های اعلامیه‌های عمومی
        $notificationIds = [1, 2, 3, 4, 5, 6]; // آیدی‌های اعلامیه‌های عمومی شما

        // ذخیره اعلانات برای هر کاربر
        foreach ($users as $user) {
            foreach ($notificationIds as $notificationId) {
                // بررسی کنید که آیا این اعلان قبلاً ذخیره شده است یا نه
                if (!UserNotification::query()->where('user_id', $user->id)->where('notification_id', $notificationId)->exists()) {
                    UserNotification::query()->create([
                        'user_id' => $user->id,
                        'notification_id' => $notificationId,
                        'is_read' => false, // وضعیت خوانده شده
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'OK',
            'message' => 'اعلانات عمومی با موفقیت ارسال شدند.',
        ]);
    }
}

//    /**
//     * @OA\Get(
//     *     path="/v1/notifications",
//     *     tags={"Notifications"},
//     *     summary="دریافت اعلانات کاربر",
//     *     @OA\Response(
//     *         response=200,
//     *         description="اعلانات با موفقیت دریافت شد",
//     *     ),
//     *     @OA\Response(
//     *         response=401,
//     *         description="کاربر احراز هویت نشده"
//     *     ),
//     *     @OA\Response(
//     *         response=403,
//     *         description="دسترسی ممنوع"
//     *     )
//     * )
//     */
//    public function index(Request $request)
//    {
//        $user = Auth::user();
//        $query = UserNotification::query()->with('notification')
//            ->where('user_id', $user->id);
//
//        // فیلتر بر اساس وضعیت خوانده شده
//        if ($request->has('is_read')) {
//            $query->where('is_read', $request->is_read);
//        }
//
//        $notifications = $query->orderBy('created_at', 'desc')->get();
//
//        return response()->json(new BaseDto(
//            BaseDtoStatusEnum::OK,
//            'اعلانات با موفقیت دریافت شد',
//            $notifications
//        ));
//    }
//
//    /**
//     * @OA\Put(
//     *     path="/v1/notifications/{id}/read",
//     *     tags={"Notifications"},
//     *     summary="خواندن اعلان خاص",
//     *     @OA\Parameter(
//     *         name="id",
//     *         in="path",
//     *         required=true,
//     *         description="ID اعلان",
//     *         @OA\Schema(type="integer")
//     *     ),
//     *     @OA\Response(
//     *         response=200,
//     *         description="اعلان به عنوان خوانده شده علامت‌گذاری شد",
//     *     ),
//     *     @OA\Response(
//     *         response=404,
//     *         description="اعلان پیدا نشد"
//     *     ),
//     *     @OA\Response(
//     *         response=400,
//     *         description="این اعلان قبلاً خوانده شده است"
//     *     )
//     * )
//     */
//    public function markAsRead(Request $request, $id)
//    {
//        $user = Auth::user();
//        $userNotification = UserNotification::query()
//            ->where('user_id', $user->id)
//            ->where('notification_id', $id)
//            ->first();
//
//        if (!$userNotification) {
//            return response()->json(new BaseDto(
//                BaseDtoStatusEnum::ERROR,
//                'اعلان پیدا نشد'
//            ), 404);
//        }
//
//        // چک کردن اینکه آیا اعلان قبلاً خوانده شده است
//        if ($userNotification->is_read) {
//            return response()->json(new BaseDto(
//                BaseDtoStatusEnum::ERROR,
//                'این اعلان قبلاً خوانده شده است'
//            ), 400);
//        }
//
//        $userNotification->is_read = true;
//        $userNotification->save();
//
//        return response()->json(new BaseDto(
//            BaseDtoStatusEnum::OK,
//            'اعلان به عنوان خوانده شده علامت‌گذاری شد'
//        ));
//    }
//
//    /**
//     * @OA\Post(
//     *     path="/v1/notifications/send",
//     *     tags={"Notifications"},
//     *     summary="ارسال اعلان",
//     *     @OA\RequestBody(
//     *         required=true,
//     *         @OA\JsonContent(
//     *             required={"user_id", "notification_id"},
//     *             @OA\Property(property="user_id", type="integer", example=1),
//     *             @OA\Property(property="notification_id", type="integer", example=1)
//     *         )
//     *     ),
//     *     @OA\Response(
//     *         response=201,
//     *         description="اعلان با موفقیت ارسال شد",
//     *     ),
//     *     @OA\Response(
//     *         response=400,
//     *         description="خطاهای اعتبارسنجی رخ داد"
//     *     )
//     * )
//     */
//    public function sendNotification(Request $request)
//    {
//        $validator = Validator::make($request->all(), [
//            'user_id' => 'required|exists:users,id',
//            'notification_id' => 'required|exists:notifications,id',
//        ]);
//
//        if ($validator->fails()) {
//            return response()->json(new BaseDto(
//                BaseDtoStatusEnum::ERROR,
//                'خطاهای اعتبارسنجی رخ داد',
//                $validator->errors()->toArray()
//            ), 400);
//        }
//
//        // چک کردن اینکه آیا اعلان قبلاً ارسال شده یا خیر
//        $existingNotification = UserNotification::query()->where('user_id', $request->user_id)
//            ->where('notification_id', $request->notification_id)
//            ->first();
//
//        if ($existingNotification) {
//            return response()->json(new BaseDto(
//                BaseDtoStatusEnum::ERROR,
//                'این اعلان قبلاً برای این کاربر ارسال شده است'
//            ), 400);
//        }
//
//        $userNotification = UserNotification::query()->create([
//            'user_id' => $request->user_id,
//            'notification_id' => $request->notification_id,
//            'is_read' => false,
//        ]);
//
//        return response()->json(new BaseDto(
//            BaseDtoStatusEnum::OK,
//            'اعلان با موفقیت ارسال شد',
//            $userNotification
//        ), 201);
//    }
//
//    /**
//     * @OA\Delete(
//     *     path="/v1/notifications/old",
//     *     tags={"Notifications"},
//     *     summary="حذف اعلانات قدیمی",
//     *     @OA\Response(
//     *         response=200,
//     *         description="اعلانات قدیمی با موفقیت حذف شد",
//     *     )
//     * )
//     */
//    public function deleteOldNotifications()
//    {
//        // تاریخ 30 روز پیش
//        $dateThreshold = now()->subDays(30);
//
//        // حذف اعلانات قدیمی
//        UserNotification::query()->where('created_at', '<', $dateThreshold)->delete();
//
//        return response()->json(new BaseDto(
//            BaseDtoStatusEnum::OK,
//            'اعلانات قدیمی با موفقیت حذف شد'
//        ));
//    }
