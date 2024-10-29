<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    protected $fillable = ['title', 'type','description'];

    public function UserNotification()
    {
        return $this->hasMany(UserNotification::class);
    }
}
