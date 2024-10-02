<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Models\Image;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash; // اضافه کردن Hash

class ProfileController extends Controller
{
    // متد نمایش عکس پروفایل
    public function index()
    {
        $user = auth()->user();

        // اگر کاربر عکس پروفایل ندارد
        if (!$user->has_avatar) {
            // اولین عکس دیفالت را از جدول images برگردانید
            $defaultAvatar = Image::query()->where('type', 'avatar')->first();
            return response()->json([
                'avatar' => $defaultAvatar ? Storage::url($defaultAvatar->path) : null,
            ]);
        }

        // اگر کاربر عکس پروفایل دارد
        $avatar = $user->avatar;
        return response()->json([
            'avatar' => $avatar ? Storage::url($avatar->path) : null,
        ]);
    }

    // متد آپلود یا ویرایش عکس پروفایل
    public function update(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user = auth()->user();

        // ذخیره عکس در مسیر مناسب
        $path = $request->file('avatar')->store('avatars', 'public');

        // ذخیره عکس در جدول images
        $image = Image::query()->create([
            'type' => 'avatar',
            'path' => $path,
            'user_id' => $user->id,
        ]);

        // به‌روزرسانی اطلاعات کاربر با استفاده از کلید خارجی عکس
        $user->update([
            'avatar_id' => $image->id,
            'has_avatar' => true,
        ]);

        return response()->json([
            'message' => 'Avatar updated successfully',
            'avatar' => Storage::url($image->path),
        ]);
    }

    // متد تنظیم یا تغییر پسورد
    public function setPassword(Request $request)
    {
        // اعتبارسنجی پسورد
        $request->validate([
            'password' => 'required|string|min:8|confirmed', // password_confirmation هم باید ارسال شود
        ]);

        // دریافت کاربر از طریق توکن احراز هویت
        $user = auth()->user();

        // تنظیم پسورد جدید و رمزگذاری آن
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password set successfully',
        ]);
    }
}
