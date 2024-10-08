<?php

namespace App\Http\Controllers\v1;

use App\Models\Otp;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProfileController extends Controller
{
    // متد نمایش عکس پروفایل
    public function index()
    {
        $user = auth()->user();

        // اگر کاربر عکس پروفایل ندارد
        if (!$user->has_avatar) {
            $defaultAvatar = Image::query()->where('type', 'avatar')->first();
            $avatarUrl = $defaultAvatar ? Storage::url($defaultAvatar->path) : null;
        } else {
            $avatar = $user->avatar;
            $avatarUrl = $avatar ? Storage::url($avatar->path) : null;
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

            // حذف تصویر قبلی
            if ($user->image_id) {
                $oldImage = Image::query()->find($user->image_id);
                if ($oldImage) {
                    Storage::disk('public')->delete($oldImage->path);
                    $oldImage->delete();
                }
            }

            // ذخیره تصویر جدید
            $filename = uniqid() . '.' . $request->file('image')->getClientOriginalExtension();
            $path = $request->file('image')->storeAs('profile_photos', $filename, 'public');

            $newImage = Image::query()->create([
                'type' => 'avatar',
                'path' => $path,
            ]);

            // به‌روزرسانی کلید خارجی در جدول users
            $user->image_id = $newImage->id;
            $user->has_avatar = true;
            $user->save();

            return response()->json([
                'data' => [
                    'image' => $filename,
                    'url' => Storage::url($path),
                    'status' => 'success',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating avatar: " . $e->getMessage());

            return response()->json([
                'message' => 'خطا در بارگذاری تصویر.',
                'error' => $e->getMessage(),
                'status' => 'error',
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        // اعتبارسنجی ورودی‌ها
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'sometimes|regex:/^09[0-9]{9}$/|digits:11',
            'profile_picture' => 'sometimes|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // به‌روزرسانی نام کاربر
        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        // به‌روزرسانی تصویر پروفایل
        if ($request->hasFile('profile_picture')) {
            // حذف تصویر قدیمی
            if ($user->image_id) {
                $oldImage = Image::query()->find($user->image_id);
                if ($oldImage) {
                    Storage::disk('public')->delete($oldImage->path);
                    $oldImage->delete();
                }
            }

            // ذخیره تصویر جدید
            $path = $request->file('profile_picture')->store('profile_pictures', 'public');
            $newImage = Image::query()->create([
                'type' => 'avatar',
                'path' => $path,
            ]);
            $user->image_id = $newImage->id;
            $user->has_avatar = true;
        }

        // مدیریت تغییر شماره تلفن
        if ($request->filled('phone') && $request->phone !== $user->phone) {
            // ارسال کد OTP به شماره جدید
            $otp = rand(1000, 9999);
            Otp::query()->create([
                'phone' => $request->phone,
                'otp' => $otp,
                'type' => 'phone_update',
            ]);
            $this->sendOtp($request->phone, $otp);

            return response()->json([
                'status' => 200,
                'message' => 'کد یکبار مصرف به شماره جدید ارسال شد. ابتدا شماره را تایید کنید.',
                'requires_verification' => true,
            ]);
        }

        // ذخیره تغییرات کاربر
        $user->save();

        return response()->json([
            'status' => 200,
            'message' => 'اطلاعات پروفایل با موفقیت به‌روزرسانی شد.',
            'user' => $user,
        ]);
    }
    public function verifyUpdatePhoneOtp (Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'otp' => 'required|digits:4',
        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }

        $otpRecord = Otp::query()
            ->where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

        if ($otpRecord) {
            $user = User::query()->firstOrCreate([
                'phone' => $request->get('phone'),
                'has_password' => false,
            ]);

            if ($user) {
                $defaultPhoto = Image::first();
                if ($defaultPhoto) {
                    $user->images()->create(['path' => $defaultPhoto->path]);
                    $user->update(['has_avatar' => true]);
                }
                $user->update(['has_account' => true]);

                $data = [
                    $message = 'با موفقیت ثبت نام شد.',
                    'status' => 200,
                    'type' => 'Registered!',
                ];


            } else {
                // اگر کاربر قبلاً ثبت‌نام شده باشد
                $data = [
                    $message = 'ورود موفقیت‌آمیز با OTP انجام شد.',
                    'type' => 'Logged in!'
                ];
            }

            $otpRecord->delete();

            try {
                $token = JWTAuth::fromUser($user);
            } catch (JWTException $e) {
                return response()->json([
                    'message' => 'مشکلی در ایجاد توکن به وجود آمده است.',
                    'status' => 'error',
                ], 500);
            }
            // پاسخ با توکن JWT
            return response()->json([
                'status' => '200',
                'data' => [
                    'token' => $token, // ارسال توکن در پاسخ
                    'user Data' => $user, // ارسال اطلاعات کاربر در پاسخ
                ],
            ]);
        } else {
            return response()->json([
                'message' => 'کد OTP نامعتبر است یا منقضی شده است.',
                'status' => 422,
            ]);
        }
    }
    public function setPassword(Request $request)
    {
        // اعتبارسنجی ورودی برای تنظیم رمز عبور
        $validator = Validator::make($request->all(), [
            'password' => [
                'required',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
            ],
            'old_password' => 'required_if:has_password,true',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = auth()->user();

        // چک کردن رمز عبور فعلی
        if ($user->has_password && !Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'status' => 400,
                'message' => 'رمز عبور فعلی نادرست است.',
            ], 400);
        }

        // تنظیم رمز عبور جدید
        $user->password = Hash::make($request->password);
        $user->has_password = true;
        $user->save();

        return response()->json([
            'status' => 200,
            'message' => 'رمز عبور با موفقیت تغییر کرد.',
        ]);
    }
        public function deleteAvatar(Request $request)
    {
        // دریافت کاربر جاری
        $user = $request->user();

        // پیدا کردن عکس پروفایل فعلی
        if ($user->image_id) {
            // پیدا کردن عکس از طریق ID
            $image = Image::query()->find($user->image_id);

            // حذف عکس از سیستم ذخیره‌سازی
            if ($image && Storage::exists($image->path)) {
                Storage::delete($image->path);
            }

            // حذف رکورد عکس از دیتابیس
            if ($image) {
                $image->delete();
            }

            // ریست کردن فیلد profile_image_id در جدول کاربران
            $user->image_id = null; // یا مقداری که نشان‌دهنده عکس پیش‌فرض است
            $user->save();

            return response()->json([
                'status' => 200,
                'data' => [
                    'message' => 'Profile picture deleted and reverted to default.'
                ],
            ]);
        }

        return response()->json([
            'status' => 404,
            'data' => [
                'message' => 'No profile picture found.'
            ],
        ]);
    }
    private function sendOtp($phone, $otp)
    {
        // ارسال OTP به شماره تلفن
        $curl = curl_init();
        $apikey = '493036347A343565484D3767455769504867546F636A7A30664D6C36316F724E38654E2B42324A2F4166633D'; // کلید API شما
        $message = urlencode("کد یکبار مصرف برای ورود شما: $otp");
        $sender = '100090003'; // شماره فرستنده شما
        $url = "https://api.kavenegar.com/v1/$apikey/sms/send.json?receptor=$phone&sender=$sender&message=$message";

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
    }
}
