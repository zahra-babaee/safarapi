<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    use HasFactory;

    protected $fillable = [
        'path',
        'type',
        'is_default',
        'user_id', // اضافه کردن user_id
        'article_id', // اضافه کردن article_id برای ارتباط با مقالات
    ];

    // رابطه با کاربر
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // رابطه با مقاله
    public function article()
    {
        return $this->belongsTo(Article::class);
    }
    // متد بررسی پیش‌فرض بودن تصویر
    public function isDefault()
    {
        return (bool) $this->is_default;
    }
    // متد برای دریافت آدرس تصویر
    public function getUrlAttribute()
    {
        return Storage::url($this->path);
    }
    // متد بررسی تعلق تصویر به کاربر خاص
    public function belongsToUser($userId)
    {
        return (int)$this->user_id === (int)$userId;
    }

}
