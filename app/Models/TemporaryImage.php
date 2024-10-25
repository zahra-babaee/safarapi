<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemporaryImage extends Model
{
    protected $fillable = [
        'path', // مسیر تصویر
    ];

    // اگر نیاز به ارتباط با کاربر داشته باشد
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

