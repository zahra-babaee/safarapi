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
                'title' => 'خوش آمدید',
                'description' => 'به سایت ما خوش آمدید.',
                'type' => 'welcome',
            ],
            [
                'title' => 'تکمیل پروفایل',
                'description' => 'لطفا حساب کاربری خود را کامل کنید.',
                'type' => 'profile_completion',
            ],
            [
                'title' => 'شماره تغییر کرد',
                'description' => 'شماره شما با موفقیت تغییر کرد.',
                'type' => 'phone_change',
            ],
            [
                'title' => 'تغییر رمز عبور',
                'description' => 'رمز عبور شما با موفقیت تغییر کرد.',
                'type' => 'password_change',
            ],
            [
                'title' => 'تغییر آواتار',
                'description' => 'آواتار شما با موفقیت تغییر کرد.',
                'type' => 'avatar_change',
            ],
            [
                'title' => 'مقاله در صف انتظار',
                'description' => 'مقاله شما در صف انتظار قرار گرفته است.',
                'type' => 'article_pending',
            ],
            [
                'title' => 'مقاله رد شد',
                'description' => 'مقاله شما رد شده است.',
                'type' => 'article_rejected',
            ],
            [
                'title' => 'مقاله تایید شد',
                'description' => 'مقاله شما تایید شده است.',
                'type' => 'article_approved',
            ],
            [
                'title' => 'مقاله منتشر شد',
                'description' => 'مقاله شما منتشر شده است.',
                'type' => 'article_published',
            ]
        ];

        // ثبت اعلانات در دیتابیس
        foreach ($notifications as $notification) {
            Notification::query()->create($notification);
        }
    }
}

