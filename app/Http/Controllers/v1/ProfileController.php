<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Models\Image;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

// اضافه کردن Hash

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
        // اعتبارسنجی درخواست
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:6|confirmed', // حداقل 6 کاراکتر و تأیید رمز عبور
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        // پیدا کردن کاربر بر اساس توکن JWT
        $user = auth()->user();

        // چک کردن اینکه آیا کاربر هنوز رمز عبور ندارد
        if (!$user->has_password) {
            // هش کردن رمز عبور
            $user->password = bcrypt($request->password);
            $user->has_password = true; // تنظیم پرچم به true
            $user->save();

            return response()->json([
                'status' => 200,
                'message' => 'رمز عبور با موفقیت تنظیم شد.',
            ]);
        }

        return response()->json([
            'status' => 400,
            'message' => 'شما قبلاً رمز عبور دارید.',
        ]);
    }
}
