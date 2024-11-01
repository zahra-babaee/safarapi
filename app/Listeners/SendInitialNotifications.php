<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Models\UserNotification;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class SendInitialNotifications
{
    public function handle(UserRegistered $event)
    {
        $user = $event->user;

        // دریافت همه‌ی اعلانات اولیه
        $notifications = Notification::all();

        DB::transaction(function () use ($notifications, $user) {
            foreach ($notifications as $notification) {
                // بررسی اینکه آیا اعلان قبلاً برای کاربر ثبت شده است یا نه
                $exists = UserNotification::query()
                    ->where('user_id', $user->id)
                    ->where('notification_id', $notification->id)
                    ->exists();

                if (!$exists) {
                    UserNotification::query()->create([
                        'user_id' => $user->id,
                        'notification_id' => $notification->id,
                        'is_read' => false,
                    ]);
                }
            }
        });
    }
}
