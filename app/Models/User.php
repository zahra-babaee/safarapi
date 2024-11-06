<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'phone',
        'name',
        'has_account',
        'has_password',
        'password',
        'has_avatar',
        'avatar',
        'image_id',
        'token',
        'deleted_at',
    ];
    public function image()
    {
        return $this->belongsTo(Image::class); // فرض بر این است که هر کاربر فقط یک تصویر دارد
    }

    public function getProfilePhoto()
    {
        $image = $this->avatar;

        if ($image && !$image->isDefault()) {
            return $image->path;
        }

        $defaultImage = Image::query()->where('type', 'avatar')->where('is_default', true)->first();
        return $defaultImage ? $defaultImage->path : 'default-avatar.png';
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function images()
    {
        return $this->hasMany(Image::class);
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function avatar()
    {
        return $this->belongsTo(Image::class, 'image_id');
    }

    public function notifications()
    {
        return $this->belongsToMany(Notification::class, 'user_notification')
            ->withPivot('is_read')
            ->withTimestamps();
    }
}
