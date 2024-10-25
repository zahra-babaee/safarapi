<?php

namespace App\Listeners;

use App\Events\NotificationEvent;
use App\Models\User;

class SendNotificationListener
{
    public function handle(NotificationEvent $event)
    {
        foreach ($event->users as $user) {
            // ارسال اعلان به کاربر
            // فرض کنید متد sendNotification برای ارسال اعلان به کاربر تعریف شده است
            $user->sendNotification($event->notification);
        }
    }
}
