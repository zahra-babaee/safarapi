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
                'description' => 'به سایت سفرکو خوش آمدید! اینجا می‌توانید بهترین اطلاعات و مقالات گردشگری را پیدا کنید. امیدواریم سفرها و تجربه‌های خاطره‌انگیزی داشته باشید.',
                'type' => 'welcome',
            ],
            [
                'title' => 'آشنایی با سفرکو',
                'description' => 'سفرکو به شما کمک می‌کند بهترین مکان‌های گردشگری را با اطلاعات کامل، عکس‌ها، و تجربه‌های دیگر کاربران کشف کنید. می‌توانید لیست علاقه‌مندی‌ها بسازید، مقالات و نظرات خود را به اشتراک بگذارید و از برنامه‌ریزی بهتر برای سفرهای خود لذت ببرید.',
                'type' => 'introduction',
            ],
            [
                'title' => 'راهنمای شروع',
                'description' => 'برای استفاده بهتر از سایت، پروفایل خود را تکمیل کنید و مقالات محبوب و مکان‌های دیدنی را کاوش کنید. همچنین می‌توانید با ثبت نظرات و امتیازدهی به مکان‌ها به سایر کاربران کمک کنید.',
                'type' => 'start',
            ],
            [
                'title' => 'به‌روز بمانید',
                'description' => 'برای دریافت جدیدترین مقالات، پیشنهادهای ویژه و رویدادهای گردشگری، در خبرنامه ما ثبت‌نام کنید. با این کار همیشه به‌روز خواهید ماند.',
                'type' => 'news',
            ],
            [
                'title' => 'تیکتها',
                'description' => '.در بخش تیکتها سوالات و مشکلات خود را مطرح کنید و پشتیبانهای ما به صورت شبانه روزی برای پاسخگویی به شما حاضرند',
                'type' => 'ticket',
            ],
            [
                'title' => 'تجربه‌های خود را به اشتراک بگذارید',
                'description' => 'می‌توانید تجربه‌های خود از مکان‌های گردشگری را در قالب مقالاتی که نوشته اید با دیگران به اشتراک بگذارید و به کاربران دیگر برای انتخاب بهتر کمک کنید. همچنین، مقالات مورد علاقه‌تان را ذخیره کنید تا در مراجعات بعدی دسترسی سریع‌تری داشته باشید.',
                'type' => 'article',
            ],
        ];

        // ثبت اعلانات در دیتابیس
        foreach ($notifications as $notification) {
            Notification::query()->create($notification);
        }
    }
}

