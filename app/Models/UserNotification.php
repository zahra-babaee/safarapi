<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $fillable = ['user_id', 'notification_id', 'is_read'];

    public function Notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function User()
    {
        return $this->belongsTo(User::class);
    }
}
