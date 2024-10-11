<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;

class NotificationSeeder extends Seeder
{
    public function run()
    {
        $notifications = [
            [
                'description' => 'خوش آمدید',
            ],
            [
                'description' => 'لطفا پروفایل خود را کامل کنید',
            ],
            [
                'description' => 'شماره با موفقیت تغییر کرد',
            ],
            [
                'description' => 'رمز عبور با موفقیت تغییر کرد',
            ],
            [
                'description' => 'آواتار با موفقیت تغییر کرد',
            ],
            [
                'description' => 'مقاله شما در صف انتظار است',
            ],
            [
                'description' => 'مقاله شما رد شد',
            ],
            [
                'description' => 'مقاله شما تایید شد',
            ],
            [
                'description' => 'مقاله شما منتشر شد',
            ],
        ];

        foreach ($notifications as $notification) {
            Notification::query()->create($notification);
        }
    }
}
