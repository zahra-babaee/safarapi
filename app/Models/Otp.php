<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'otp',
        'type',
        'expires_at'
    ];
    public function generateMessage()
    {
        switch ($this->type) {
            case 'register':
                return "کد تأیید ثبت‌نام شما: {$this->otp}";
            case 'forget':
                return "کد بازیابی رمز عبور شما: {$this->otp}";
            case 'update':
                return "کد تأیید بروزرسانی اطلاعات شما: {$this->otp}";
            case 'old':
                return "کد تأیید مورد استفاده قبلی شما: {$this->otp}";
            default:
                return "کد OTP شما: {$this->otp}";
        }
    }
}
