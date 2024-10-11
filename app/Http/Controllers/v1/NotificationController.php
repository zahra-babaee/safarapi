<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Models\Notification;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/v1/notifications",
     *     tags={"Notifications"},
     *     summary="دریافت اعلانات کاربر",
     *     @OA\Response(
     *         response=200,
     *         description="اعلانات با موفقیت دریافت شد",
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="کاربر احراز هویت نشده"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="دسترسی ممنوع"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = UserNotification::query()->with('notification')
            ->where('user_id', $user->id);

        // فیلتر بر اساس وضعیت خوانده شده
        if ($request->has('is_read')) {
            $query->where('is_read', $request->is_read);
        }

        $notifications = $query->orderBy('created_at', 'desc')->get();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'اعلانات با موفقیت دریافت شد',
            $notifications
        ));
    }

    /**
     * @OA\Put(
     *     path="/v1/notifications/{id}/read",
     *     tags={"Notifications"},
     *     summary="خواندن اعلان خاص",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID اعلان",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="اعلان به عنوان خوانده شده علامت‌گذاری شد",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="اعلان پیدا نشد"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="این اعلان قبلاً خوانده شده است"
     *     )
     * )
     */
    public function markAsRead(Request $request, $id)
    {
        $user = Auth::user();
        $userNotification = UserNotification::query()
            ->where('user_id', $user->id)
            ->where('notification_id', $id)
            ->first();

        if (!$userNotification) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'اعلان پیدا نشد'
            ), 404);
        }

        // چک کردن اینکه آیا اعلان قبلاً خوانده شده است
        if ($userNotification->is_read) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'این اعلان قبلاً خوانده شده است'
            ), 400);
        }

        $userNotification->is_read = true;
        $userNotification->save();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'اعلان به عنوان خوانده شده علامت‌گذاری شد'
        ));
    }

    /**
     * @OA\Post(
     *     path="/v1/notifications/send",
     *     tags={"Notifications"},
     *     summary="ارسال اعلان",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "notification_id"},
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="notification_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="اعلان با موفقیت ارسال شد",
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی رخ داد"
     *     )
     * )
     */
    public function sendNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'notification_id' => 'required|exists:notifications,id',
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داد',
                $validator->errors()->toArray()
            ), 400);
        }

        // چک کردن اینکه آیا اعلان قبلاً ارسال شده یا خیر
        $existingNotification = UserNotification::query()->where('user_id', $request->user_id)
            ->where('notification_id', $request->notification_id)
            ->first();

        if ($existingNotification) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'این اعلان قبلاً برای این کاربر ارسال شده است'
            ), 400);
        }

        $userNotification = UserNotification::query()->create([
            'user_id' => $request->user_id,
            'notification_id' => $request->notification_id,
            'is_read' => false,
        ]);

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'اعلان با موفقیت ارسال شد',
            $userNotification
        ), 201);
    }

    /**
     * @OA\Delete(
     *     path="/v1/notifications/old",
     *     tags={"Notifications"},
     *     summary="حذف اعلانات قدیمی",
     *     @OA\Response(
     *         response=200,
     *         description="اعلانات قدیمی با موفقیت حذف شد",
     *     )
     * )
     */
    public function deleteOldNotifications()
    {
        // تاریخ 30 روز پیش
        $dateThreshold = now()->subDays(30);

        // حذف اعلانات قدیمی
        UserNotification::query()->where('created_at', '<', $dateThreshold)->delete();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'اعلانات قدیمی با موفقیت حذف شد'
        ));
    }
}
