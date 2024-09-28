<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;
    protected $fillable = [
        'phone',
        'otp'
    ];

    public static function remainingTime($phone, $minutes = 2)
    {
        // آخرین OTP برای شماره تلفن
        $lastOtp = self::query()->where('phone', $phone)->orderBy('created_at', 'desc')->first();
//        $lastOtp = self::where('phone', $phone)->orderBy('created_at', 'desc')->first();

        // اگر آخرین کدی وجود داشت و زمان آن کمتر از n دقیقه بود
        if ($lastOtp && $lastOtp->created_at >= now()->subMinutes($minutes)) {
            // محاسبه زمان باقی‌مانده
            $remainingSeconds = $lastOtp->created_at->addMinutes($minutes)->diffInSeconds(now());

            return $remainingSeconds;
        }

        // اگر زمان باقی‌مانده وجود نداشته باشد (یعنی محدودیت زمانی گذشته باشد)
        return 0;
    }
}
