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
        'user_id',
        'article_id', // اضافه کردن article_id برای ارتباط با مقالات
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function isDefault()
    {
        return (bool) $this->is_default;
    }

    public function getUrlAttribute()
    {
        return Storage::url($this->path);
    }
}

