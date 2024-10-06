<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory; // اضافه کردن این خط برای استفاده از قابلیت‌های کارخانه

    protected $fillable = [
        'path',
        'type',
        'is_default', // اضافه کردن این خط برای فیلد is_default
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isDefault() // متد جدید برای بررسی پیش‌فرض بودن تصویر
    {
        return $this->is_default;
    }
}
