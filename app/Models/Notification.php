<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'title', 'type', 'description'
    ];

    // ارتباط بسیاری-به-بسیاری با کاربران از طریق جدول میانی
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_notification')
            ->withPivot('is_read')
            ->withTimestamps();
    }
    public function userNotifications()
    {
        return $this->hasMany(UserNotification::class);
    }
}
