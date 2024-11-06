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
}
