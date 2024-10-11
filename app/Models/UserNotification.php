<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    use HasFactory;

    protected $table = 'user_notification'; // نام جدول در دیتابیس

    protected $fillable = ['user_id', 'notification_id', 'is_read'];

    // ارتباط با مدل User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ارتباط با مدل Notification
    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}

