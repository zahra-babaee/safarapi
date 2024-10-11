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

    public static function remainingTime($phone, $type)
    {
        $otp = self::query()->where('phone', $phone)->where('type', $type)->orderBy('created_at', 'desc')->first();

        if ($otp) {
            $createdAt = $otp->created_at;
            $expirationTime = $createdAt->addMinutes(2); // فرض بر این است که OTP برای 2 دقیقه معتبر است
            return $expirationTime->diffInSeconds(now());
        }

        return 0; // اگر OTP وجود نداشته باشد
    }
}
