<?php

namespace App\Http\Controllers\v1;

use App\Models\Otp;
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
            $avatarUrl = $defaultAvatar ? Storage::url($defaultAvatar->url) : null;
        } else {
            // اگر کاربر عکس پروفایل دارد
            $avatar = $user->avatar;
            $avatarUrl = $avatar ? Storage::url($avatar->url) : null;
        }

        return response()->json([
            'name' => $user->name,
            'phone' => $user->phone,
            'avatar' => $avatarUrl,
        ]);
    }
    public function updateAvatar(Request $request)
    {
        // اعتبارسنجی ورودی
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors(),
                'status' => 'error',
            ], 400);
        }

        try {
            $user = auth()->user();

            // اگر کاربر قبلاً عکسی داشته باشد، آن را حذف کنید
            if ($user->image_id) {
                $oldImage = Image::query()->find($user->image_id);
                if ($oldImage) {
                    // حذف تصویر از ذخیره‌سازی
                    Storage::disk('public')->delete($oldImage->path);
                    // حذف رکورد تصویر قدیمی از جدول images
                    $oldImage->delete();
                }
            }

            // ذخیره تصویر جدید در جدول images
            $filename = uniqid() . '.' . $request->file('image')->getClientOriginalExtension();
            $path = $request->file('image')->storeAs('profile_photos', $filename, 'public');

            $newImage = Image::query()->create([
                'type' => 'avatar',
                'path' => $path,
            ]);

            // به‌روزرسانی کلید خارجی در جدول users
            $user->image_id = $newImage->id;
            $user->save();

            return response()->json([
                'data' => [
                    'image' => $filename,
                    'url' => Storage::url($path),
                    'status' => 'success',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'خطا در بارگذاری تصویر.',
                'error' => $e->getMessage(),
                'status' => 'error',
            ], 500);
        }
    }
    public function updateProfile(Request $request)
    {
        // دریافت کاربر از JWT Token
        $user = auth()->user();

        // اعتبارسنجی ورودی‌ها
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|regex:/^09[0-9]{9}$/|digits:11',
//            'password' => 'sometimes|required_with:old_password|confirmed|min:6',
//            'old_password' => 'sometimes|required_with:password',
            'profile_picture' => 'sometimes|image|max:2048', // محدودیت سایز 2MB
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        // آپدیت نام و عکس پروفایل
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('profile_pictures', 'public');
            $user->profile_picture = $path;
        }

        // مدیریت تغییر شماره تلفن
        if ($request->has('phone') && $request->phone != $user->phone) {
            // ارسال کد OTP برای تایید شماره تلفن جدید
            $otp = rand(1000, 9999);
            Otp::query()->create([
                'phone' => $request->phone,
                'otp' => $otp,
                'type' => 'phone_update',
            ]);
            $this->sendOtp($request->phone, $otp);

            // بازگرداندن پیام ارسال OTP
            return response()->json([
                'status' => 200,
                'message' => 'کد یکبار مصرف به شماره جدید ارسال شد. ابتدا شماره را تایید کنید.',
                'requires_verification' => true,
            ]);
        }

        // مدیریت تغییر رمز عبور
        if ($request->has('password')) {
            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'رمز عبور فعلی نادرست است.'
                ], 400);
            }
            $user->password = Hash::make($request->password);
        }

        // ذخیره تغییرات
        $user->save();

        return response()->json([
            'status' => 200,
            'message' => 'اطلاعات پروفایل با موفقیت به‌روزرسانی شد.',
            'user' => $user,
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
    private function sendOtp($phone, $otp)
    {
        // ارسال OTP به شماره تلفن
        $curl = curl_init();
        $apikey = '493036347A343565484D3767455769504867546F636A7A30664D6C36316F724E38654E2B42324A2F4166633D'; // کلید API شما
        $message = urlencode("کد یکبار مصرف برای ورود شما: $otp");
//        $phone = $request->phone;
        $sender = '100090003'; // شماره فرستنده شما
        $url = "https://api.kavenegar.com/v1/$apikey/sms/send.json?receptor=$phone&sender=$sender&message=$message";

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Cookie: cookiesession1=678A8C40E277BD0A60A9819053EC3D82'
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    }
}
