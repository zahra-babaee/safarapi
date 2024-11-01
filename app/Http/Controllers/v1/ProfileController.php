<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Models\Otp;
use App\Models\Image;
use App\Models\User;
use App\Notifications\ProfileStatusUpdated;
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
    /**
     * @OA\Get(
     *     path="/v1/profile",
     *     summary="نمایش پروفایل کاربر",
     *     description="این متد اطلاعات پروفایل کاربر شامل نام، شماره تلفن و عکس پروفایل را نمایش می‌دهد. اگر کاربر عکس پروفایل نداشته باشد، آواتار پیش‌فرض از جدول تصاویر بازیابی و نمایش داده می‌شود.",
     *     tags={"پروفایل"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="اطلاعات پروفایل با موفقیت بازیابی شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="پروفایل کاربر بازیابی شد."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="name", type="string", example="علی احمدی"),
     *                 @OA\Property(property="phone", type="string", example="09123456789"),
     *                 @OA\Property(property="avatar", type="string", nullable=true, example="https://your-domain.com/images/default.png")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="احراز هویت ناموفق بود.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="توکن نامعتبر است.")
     *         )
     *     )
     * )
     */
    public function index()
    {
        $user = auth()->user();

        // اگر کاربر عکس پروفایل ندارد از آواتار پیش‌فرض استفاده می‌کنیم
        if (!$user->has_avatar) {
            $defaultAvatar = Image::query()->where('type', 'default_avatar')->first();
            $avatarUrl = $defaultAvatar ? asset('images/' . $defaultAvatar->path) : null;
        } else {
            $avatar = Image::query()->find($user->image_id);
            $avatarUrl = $avatar ? asset('images/' . $avatar->path) : null;
        }

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            data: [
                'name' => $user->name,
                'phone' => $user->phone,
                'avatar' => $avatarUrl,
            ]
        ));
    }
    /**
     * @OA\Post(
     *     path="/v1/profile/avatar",
     *     summary="به‌روزرسانی عکس پروفایل کاربر",
     *     description="این متد برای به‌روزرسانی عکس پروفایل کاربر استفاده می‌شود. عکس جدید باید یک فایل تصویر معتبر باشد و حجم آن نباید از 2 مگابایت بیشتر باشد. در صورت وجود عکس قبلی، آن حذف شده و عکس جدید جایگزین می‌شود. تصویر پروفایل در پوشه `images/profile_photos` ذخیره می‌شود.",
     *     tags={"پروفایل"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="image", type="string", format="binary", description="فایل تصویر پروفایل"),
     *                 required={"image"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="عکس پروفایل با موفقیت به‌روزرسانی شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="عکس با موفقیت به‌روزرسانی شد"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="image", type="string", example="unique_image_name.jpg"),
     *                 @OA\Property(property="url", type="string", example="https://your-domain.com/images/profile_photos/unique_image_name.jpg")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی تصویر رخ داده است.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی تصویر رخ داده است."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطایی در بارگذاری تصویر رخ داد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطا در بارگذاری تصویر.")
     *         )
     *     )
     * )
     */
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

            // حذف تصویر قبلی در صورت وجود
            if ($user->image_id) {
                $oldImage = Image::query()->find($user->image_id);
                if ($oldImage) {
                    Storage::disk('public')->delete($oldImage->path);
                    $oldImage->delete();
                }
            }

            // ذخیره تصویر جدید
            $profileImage = $request->file('image');
            $imageName = time() . '.' . $profileImage->extension();
            $profileImage->move(public_path('images/profile_photos'), $imageName);

            $path = 'images/profile_photos/' . $imageName;

            // ذخیره رکورد تصویر جدید در دیتابیس
            $newImage = Image::query()->create([
                'type' => 'avatar',
                'path' => $path,
            ]);

            // به‌روزرسانی کلید خارجی در جدول کاربران
            $user->image_id = $newImage->id;
            $user->has_avatar = true;
            $user->save();

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::OK,
                'عکس با موفقیت به‌روزرسانی شد',
                data: [
                    'image' => $imageName,
                    'url' => asset($path),
                ]
            ), 200);

        } catch (\Exception $e) {
            Log::error("Error updating avatar: " . $e->getMessage());

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطا در بارگذاری تصویر.',
                data: [
                    'error' => $e->getMessage(),
                    'status' => 'error',
                ]
            ), 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/v1/profile/update",
     *     summary="به‌روزرسانی پروفایل کاربر",
     *     description="این متد برای به‌روزرسانی اطلاعات پروفایل کاربر از جمله نام، تصویر پروفایل و شماره تلفن استفاده می‌شود. در صورت تغییر شماره تلفن، کد یکبار مصرف برای تایید ارسال خواهد شد.",
     *     tags={"پروفایل"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="نام کاربر", example="علی رضایی"),
     *             @OA\Property(property="phone", type="string", description="شماره تلفن جدید", example="09123456789"),
     *             @OA\Property(property="image", type="string", format="binary", description="تصویر پروفایل جدید")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="پروفایل با موفقیت به‌روزرسانی شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="اطلاعات پروفایل با موفقیت به‌روزرسانی شد."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", type="object", description="اطلاعات کاربر")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="کد یکبار مصرف به شماره جدید ارسال شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="کد یکبار مصرف به شماره جدید ارسال شد.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی رخ داده است یا شماره تلفن قبلاً استفاده شده است.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی رخ داده است یا این شماره تلفن قبلاً توسط کاربر دیگری استفاده شده است."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطا در به‌روزرسانی اطلاعات پروفایل.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطایی در به‌روزرسانی اطلاعات رخ داده است.")
     *         )
     *     )
     * )
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        // اعتبارسنجی ورودی‌ها
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|regex:/^09[0-9]{9}$/|digits:11',
            'image' => 'sometimes|image|max:2048',
        ], [
            'name.string' => 'نام باید معتبر باشد.',
            'name.max' => 'نام نباید بیشتر از 255 کاراکتر باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.',
            'image.image' => 'فایل باید یک تصویر معتبر باشد.',
            'image.max' => 'حجم تصویر نباید بیشتر از 2 مگابایت باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $validator->errors()->toArray()
            ), 400);
        }

        // به‌روزرسانی نام کاربر در صورت ارسال
        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        // به‌روزرسانی تصویر پروفایل در صورت ارسال
        if ($request->hasFile('image')) {
            $profile_image = $request->file('image');
            $imageName = time() . '.' . $profile_image->extension();

            // حذف تصویر قدیمی
            if ($user->image_id) {
                $oldImage = Image::query()->find($user->image_id);
                if ($oldImage) {
                    Storage::disk('public')->delete($oldImage->path);
                    $oldImage->delete();
                }
            }

            // ذخیره تصویر جدید به مسیر public/images
            $profile_image->move(public_path('images'), $imageName);
            $path = 'images/' . $imageName;

            $newImage = Image::query()->create([
                'type' => 'avatar',
                'path' => $path,
                'user_id' => $user->id,
            ]);

            $user->avatar = $path;
            $user->image_id = $newImage->id;
            $user->has_avatar = true;
        }

        // به‌روزرسانی شماره تلفن در صورت ارسال
        if ($request->filled('phone') && $request->phone !== $user->phone) {
            // بررسی اینکه شماره وارد شده قبلاً توسط کاربر دیگری استفاده شده است
            $existingUser = User::query()->where('phone', $request->phone)->first();
            if ($existingUser) {
                return response()->json(new BaseDto(
                    BaseDtoStatusEnum::ERROR,
                    'این شماره تلفن قبلاً توسط کاربر دیگری استفاده شده است.'
                ), 400);
            }

            // تولید و ذخیره OTP
            $otp = rand(1000, 9999);
            Otp::query()->create([
                'phone' => $request->phone,
                'otp' => $otp,
                'type' => 'phone_update',
            ]);
            $this->sendOtp($request->phone, $otp);

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::OK,
                'کد یکبار مصرف به شماره جدید ارسال شد..',
            ), 202);
        }

        // ذخیره تغییرات کاربر
        $user->save();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'اطلاعات پروفایل با موفقیت به‌روزرسانی شد.',
            ['user' => $user]
        ), 200);
    }
    /**
     * @OA\Post(
     *     path="/v1/profile/verify-update",
     *     summary="تأیید شماره تلفن با کد OTP",
     *     description="این متد برای تأیید شماره تلفن کاربر با استفاده از کد یکبار مصرف (OTP) است. در صورت موفقیت، یک توکن JWT برای کاربر تولید می‌شود.",
     *     tags={"پروفایل"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="phone", type="string", description="شماره تلفن کاربر", example="09123456789"),
     *             @OA\Property(property="otp", type="string", description="کد یکبار مصرف", example="1234")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="شماره تلفن با موفقیت بروزرسانی شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="شماره تلفن با موفقیت بروزرسانی شد."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", description="توکن JWT کاربر"),
     *                 @OA\Property(
     *                     property="user_data",
     *                     type="object",
     *                     description="اطلاعات کاربر شامل شناسه و شماره تلفن"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی رخ داده است.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی رخ داده است."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="احراز هویت نامعتبر است.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="احراز هویت کاربر نامعتبر است.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="کد OTP نامعتبر است یا منقضی شده است.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="کد OTP نامعتبر است یا منقضی شده است.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="مشکلی در ایجاد توکن به وجود آمده است.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="مشکلی در ایجاد توکن به وجود آمده است.")
     *         )
     *     )
     * )
     */
    public function verifyUpdatePhoneOtp (Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'otp' => 'required|digits:4',
        ], [
            'phone.required' => 'شماره تلفن نباید خالی باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروث شود و 11 رقم باشد.',
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
    /**
     * @OA\Post(
     *     path="/v1/profile/set-password",
     *     summary="تنظیم رمز عبور",
     *     description="این متد برای تنظیم یا تغییر رمز عبور کاربر استفاده می‌شود. در صورت وجود رمز عبور قبلی، کاربر باید آن را وارد کند.",
     *     tags={"پروفایل"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="password", type="string", description="رمز عبور جدید", example="NewPassword@123"),
     *             @OA\Property(property="password_confirmation", type="string", description="تأیید رمز عبور جدید", example="NewPassword@123"),
     *             @OA\Property(property="old_password", type="string", description="رمز عبور قبلی (در صورت داشتن رمز عبور)", example="OldPassword@123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="رمز عبور با موفقیت ایجاد شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="رمز عبور با موفقیت ایجاد شد.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی رخ داده است.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی رخ داده است."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 additionalProperties=@OA\Property(type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="رمز عبور فعلی نادرست است.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="رمز عبور فعلی نادرست است.")
     *         )
     *     )
     * )
     */
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
                'regex:/[@$!%*#?&]/'
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
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, 'رمز عبور فعلی نادرست است.'),
                401); // تغییر کد وضعیت به 401
        }

        // تنظیم رمز عبور جدید
        $user->password = Hash::make($request->password);
        $user->has_password = true; // در صورتی که رمز عبور قبلاً نداشته باشد، این مقدار به true تغییر می‌کند
        $user->save();

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, 'رمز عبور با موفقیت ایجاد شد.'),
            200);
    }
    /**
     * @OA\Delete(
     *     path="/v1/profile/delete-avatar",
     *     summary="حذف عکس پروفایل",
     *     description="این متد برای حذف عکس پروفایل کاربر استفاده می‌شود. در صورت حذف موفق، عکس پیش‌فرض جایگزین می‌شود.",
     *     tags={"پروفایل"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="عکس پروفایل با موفقیت حذف شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="عکس پروفایل حذف شد و عکس پیش‌فرض جایگزین شد.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="عکس پروفایل پیدا نشد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="عکس پروفایل پیدا نشد.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطا در حذف عکس پروفایل.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطا در حذف عکس پروفایل.")
     *         )
     *     )
     * )
     */
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

            // ریست کردن فیلد image_id در جدول کاربران
            $user->image_id = null; // یا مقداری که نشان‌دهنده عکس پیش‌فرض است
            $user->save();

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, 'عکس پروفایل حذف شد و عکس پیش‌فرض جایگزین شد.'),
                200);
        }

        return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, 'عکس پروفایل پیدا نشد.'),
            404);
    }
    private function sendOtp($phone, $otp)
    {
        // ارسال OTP به شماره تلفن
        $curl = curl_init();
        $apikey = ''; // کلید API شما
        $message = urlencode("کد یکبار مصرف برای ورود شما: $otp");
        $sender = ''; // شماره فرستنده شما
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
