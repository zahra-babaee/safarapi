<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Models\Otp;
use App\Models\Image;
use App\Models\User;
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

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data:[
            'name' => $user->name,
            'phone' => $user->phone,
            'avatar' => $avatarUrl,
        ]));
    }

    public function updateAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|max:2048',
        ], [
            'image.required' => 'عکس نباید خالی باشد.',
            'image.image' => 'فایل باید یک تصویر معتبر باشد.',
            'image.max' => 'حجم تصویر نباید بیشتر از 2 مگابایت باشد.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی تصویر رخ داده است.',
                $errors
            ), 400);
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

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, 'عکس با موفقیت بزوررسانی شد',
                data: [
                    'image' => $filename,
                    'url' => Storage::url($path),
                ]), 200);

        } catch (\Exception $e) {
            Log::error("Error updating avatar: " . $e->getMessage());

            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR,  'خطا در بارگذاری تصویر.',
                data:[
                'error' => $e->getMessage(),
                'status' => 'error',
            ]), 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'sometimes|regex:/^09[0-9]{9}$/|digits:11',
            'profile_picture' => 'sometimes|image|max:2048'
        ], [
            'name.required' => 'نام نباید خالی باشد.',
            'name.string' => 'نام باید معتبر باشد.',
            'name.max' => 'نام نباید بیشتر از 255 کاراکتر باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.',
            'profile_picture.image' => 'فایل باید یک تصویر معتبر باشد.',
            'profile_picture.max' => 'حجم تصویر نباید بیشتر از 2 مگابایت باشد.'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $errors
            ), 400);
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

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, 'کد یکبار مصرف به شماره جدید ارسال شد. ابتدا شماره را تایید کنید.',
                data:
                [
                'requires_verification' => true,
                ]),200);
        }

        // ذخیره تغییرات کاربر
        $user->save();

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, 'اطلاعات پروفایل با موفقیت به‌روزرسانی شد.',
           data:
            [
            'user' => $user,
            ]),200);
    }
    public function verifyUpdatePhoneOtp (Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'otp' => 'required|digits:4',
        ], [
            'phone.required' => 'شماره تلفن نباید خالی باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.',
            'otp.required' => 'کدیکبار مصرف را وارد کنید.',
            'otp.digits' => 'کدیکبار مصرف باید 4 رقم باشد'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $errors
            ), 400);
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
            }

            $otpRecord->delete();

            try {
                $token = JWTAuth::fromUser($user);
            } catch (JWTException $e) {
                return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, 'مشکلی در ایجاد توکن به وجود آمده است.'),
                    500);
            }
            // پاسخ با توکن JWT
            return response()->json(new BaseDto(BaseDtoStatusEnum::OK,'شماره تلفن با موفقیت بروزرسانی شد.',
               data:
                [
                    'token' => $token, // ارسال توکن در پاسخ
                    'user Data' => $user, // ارسال اطلاعات کاربر در پاسخ
                ]),200);
        } else {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR,'کد OTP نامعتبر است یا منقضی شده است.',
            ),422);
        }
    }
    public function setPassword(Request $request)
    {
        // اعتبارسنجی ورودی برای تنظیم رمز عبور
        $validator = Validator::make($request->all(), [
            'password' => [
                'required','min:8','confirmed','regex:/[a-z]/','regex:/[A-Z]/','regex:/[0-9]/','regex:/[@$!%*#?&]/'
                ],
            'old_password' => 'required_if:has_password,true',
        ], [
            'password.required' => 'رمز عبور نباید خالی باشد.',
            'password.min' => 'رمز عبور باید حداقل 8 کاراکتر باشد.',
            'password.confirmed' => 'رمز عبور و تأیید رمز عبور باید یکسان باشند.',
            'password.regex' => 'رمز عبور باید شامل حداقل یک حرف کوچک، یک حرف بزرگ، یک عدد و یک کاراکتر خاص باشد.',
            'old_password.required_if' => 'برای تغییر رمز عبور، باید رمز عبور قبلی را وارد کنید.',
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $validator->errors()
            ), 400);
        }

$user = auth()->user();

        // چک کردن رمز عبور فعلی
        if ($user->has_password && !Hash::check($request->old_password, $user->password)) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR,'رمز عبور فعلی نادرست است.'),
                400);
        }

        // تنظیم رمز عبور جدید
        $user->password = Hash::make($request->password);
        $user->has_password = true;
        $user->save();

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK,'رمز عبور با موفقیت تغییر کرد.'),
            200);
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

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, 'عکس پروفایل حذف شد و عکس پیشفرض جایگزین شد.'),
                200);
        }

        return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR,'عکس پروفایل پیدا نشد.'),
            404);
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
