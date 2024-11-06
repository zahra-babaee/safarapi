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
     * @OA\Post (
     *     path="/api/v1/update-name",
     *     summary="بروزرسانی نام کاربر",
     *     description="این api نام کاربر را بروزرسانی میکند",
     *     tags={"پروفایل"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="John Doe", description="New name for the user")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Name updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Name updated successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The name field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function updateName(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ], [
            'name.required' => 'نام نباید خالی باشد.',
            'name.string' => 'نام باید معتبر باشد.',
            'name.max' => 'نام نباید بیشتر از 255 کاراکتر باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی نام رخ داده است.',
                $validator->errors()->toArray()
            ), 400);
        }

        $user = auth()->user();
        $user->name = $request->name;
        $user->save();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'نام با موفقیت به‌روزرسانی شد.',
            ['name' => $user->name]
        ), 200);
    }
    /**
     * @OA\Post(
     *     path="/api/v1/old/phone",
     *     summary="ارسال کد OTP به شماره تلفن قدیمی",
     *     tags={"Phone Verification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="09123456789", description="شماره تلفن کاربر که باید با 09 شروع شود و 11 رقم باشد")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="کد OTP ارسال شد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="کد ارسال شد."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="phone", type="string", example="09123456789"),
     *                 @OA\Property(property="otp_ttl", type="integer", example=120)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی شماره تلفن",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی شماره تلفن رخ داده است."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="درخواست بیش از حد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="تا {remainingSeconds} ثانیه دیگر نمی‌توانید درخواست جدید بدهید."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="otp_ttl", type="integer", example=60)
     *             )
     *         )
     *     )
     * )
     */
    public function oldPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
        ], [
            'phone.required' => 'شماره تلفن نباید خالی باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی شماره تلفن رخ داده است.',
                $validator->errors()->toArray()
            ), 400);
        }
        $otpValidityDuration = 120; // اعتبار OTP به ثانیه
        $lastOtp = Otp::query()->where('phone', $request->phone)->orderBy('created_at', 'desc')->first();

        // اگر آخرین OTP وجود دارد
        if ($lastOtp) {
            $timeSinceLastOtp = now()->timestamp - $lastOtp->created_at->timestamp;

            if ($timeSinceLastOtp < $otpValidityDuration) {
                $remainingSeconds = $otpValidityDuration - $timeSinceLastOtp;

                return response()->json(new BaseDto(
                    BaseDtoStatusEnum::ERROR,
                    "تا {$remainingSeconds} ثانیه دیگر نمی‌توانید درخواست جدید بدهید.",
                    data: [
                        'otp_ttl' => $remainingSeconds,
//                            'has_account' => (bool)$user,
                    ],
                ), 429); // کد وضعیت 429 برای Too Many Requests
            }
        }

        // تولید و ذخیره OTP
        $otp = rand(1000, 9999);
        Otp::query()->create([
            'phone' => $request->phone,
            'otp' => $otp,
            'type' => 'old',
        ]);

        // ارسال OTP به کاربر
        $this->sendOtp($request->phone, $otp);

        // پیامی برای ارسال OTP جدید
        $message = $lastOtp ? 'کد جدید ارسال شد.' : 'کد ارسال شد.';

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            $message,
            data: [
                'phone' => $request->phone,
                'otp_ttl' => $otpValidityDuration,
            ]
        ), 200);
    }
    /**
     * @OA\Post(
     *     path="/api/v1/old/phone/verify",
     *     summary="تأیید شماره تلفن قدیمی با استفاده از OTP",
     *     tags={"Phone Verification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "otp"},
     *             @OA\Property(property="phone", type="string", example="09123456789", description="شماره تلفن کاربر"),
     *             @OA\Property(property="otp", type="string", example="1234", description="کد OTP دریافت شده")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="کد تایید شد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="کد تایید شد.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="کد OTP نامعتبر یا منقضی شده است",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="کد OTP نامعتبر است یا منقضی شده است.")
     *         )
     *     )
     * )
     */
    public function verifyOldPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'otp' => 'required|digits:4',
        ], [
            'phone.required' => 'شماره تلفن نباید خالی باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.',
            'otp.required' => 'کد یکبار مصرف را وارد کنید.',
            'otp.digits' => 'کد یکبار مصرف باید 4 رقم باشد.'
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $validator->errors()->toArray()
            ), 400);
        }
        $otpRecord = Otp::query()
            ->where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

        if (!$otpRecord) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'کد OTP نامعتبر است یا منقضی شده است.'
            ), 422);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/v1/update/phone",
     *     summary="درخواست تغییر شماره تلفن",
     *     tags={"Phone Update"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="09123456789", description="شماره جدید کاربر")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="کد OTP به شماره جدید ارسال شد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="کد جدید ارسال شد."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="phone", type="string", example="09123456789"),
     *                 @OA\Property(property="otp_ttl", type="integer", example=120)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی شماره تلفن",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی شماره تلفن رخ داده است."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="درخواست بیش از حد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="تا {remainingSeconds} ثانیه دیگر نمی‌توانید درخواست جدید بدهید."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="otp_ttl", type="integer", example=60)
     *             )
     *         )
     *     )
     * )
     */
    public function updatePhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
        ], [
            'phone.required' => 'شماره تلفن نباید خالی باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی شماره تلفن رخ داده است.',
                $validator->errors()->toArray()
            ), 400);
        }

        $user = auth()->user();

        if ($request->phone !== $user->phone) {
            // بررسی اینکه شماره وارد شده قبلاً توسط کاربر دیگری استفاده شده است
            $existingUser = User::query()->where('phone', $request->phone)->first();
            if ($existingUser) {
                return response()->json(new BaseDto(
                    BaseDtoStatusEnum::ERROR,
                    'این شماره تلفن قبلاً توسط کاربر دیگری استفاده شده است.'
                ), 409);
            }

            $otpValidityDuration = 120; // اعتبار OTP به ثانیه
            $lastOtp = Otp::query()->where('phone', $request->phone)->orderBy('created_at', 'desc')->first();

            // اگر آخرین OTP وجود دارد
            if ($lastOtp) {
                $timeSinceLastOtp = now()->timestamp - $lastOtp->created_at->timestamp;

                if ($timeSinceLastOtp < $otpValidityDuration) {
                    $remainingSeconds = $otpValidityDuration - $timeSinceLastOtp;

                    return response()->json(new BaseDto(
                        BaseDtoStatusEnum::ERROR,
                        "تا {$remainingSeconds} ثانیه دیگر نمی‌توانید درخواست جدید بدهید.",
                        data: [
                            'otp_ttl' => $remainingSeconds,
//                            'has_account' => (bool)$user,
                            'has_password' => $user ? $user->has_password : false,
                        ],
                    ), 429); // کد وضعیت 429 برای Too Many Requests
                }
            }

            // تولید و ذخیره OTP
            $otp = rand(1000, 9999);
            Otp::query()->create([
                'phone' => $request->phone,
                'otp' => $otp,
                'type' => 'update',
            ]);

            // ارسال OTP به کاربر
            $this->sendOtp($request->phone, $otp);

            // پیامی برای ارسال OTP جدید
            $message = $lastOtp ? 'کد جدید ارسال شد.' : 'کد ارسال شد.';

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::OK,
                $message,
                data: [
                    'phone' => $request->phone,
                    'otp_ttl' => $otpValidityDuration,
//                    'has_account' => (bool)$user,
                    'has_password' => $user ? $user->has_password : false,
                ]
            ), 200);
        }

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::ERROR,
            'شماره جدید باید با شماره فعلی تفاوت داشته باشد.'
        ), 409);
    }
    /**
     * @OA\Post(
     *     path="/api/v1/update/phone/verify",
     *     summary="تأیید شماره تلفن جدید با استفاده از OTP",
     *     tags={"Phone Update"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "otp"},
     *             @OA\Property(property="phone", type="string", example="09123456789", description="شماره جدید کاربر"),
     *             @OA\Property(property="otp", type="string", example="1234", description="کد OTP دریافت شده")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="شماره تلفن با موفقیت بروزرسانی شد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="شماره تلفن با موفقیت بروزرسانی شد."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string", example="JWT_TOKEN"),
     *                 @OA\Property(property="user_data", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="کد OTP نامعتبر یا منقضی شده است",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="کد OTP نامعتبر است یا منقضی شده است.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="کاربر یافت نشد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="کاربر یافت نشد.")
     *         )
     *     )
     * )
     */
    public function verifyNewPhoneOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'otp' => 'required|digits:4',
        ], [
            'phone.required' => 'شماره تلفن نباید خالی باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.',
            'otp.required' => 'کد یکبار مصرف را وارد کنید.',
            'otp.digits' => 'کد یکبار مصرف باید 4 رقم باشد.'
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $validator->errors()->toArray()
            ), 400);
        }

        $otpRecord = Otp::query()
            ->where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

        if (!$otpRecord) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'کد OTP نامعتبر است یا منقضی شده است.'
            ), 422);
        }

        // دریافت کاربر فعلی از طریق احراز هویت
        $user = auth()->user();

        if ($user) {
            // بروزرسانی شماره تلفن و وضعیت حساب کاربری
            $user->update([
                'phone' => $request->phone,
                'has_account' => true,
                'has_password' => false,
            ]);

            $otpRecord->delete(); // حذف OTP پس از استفاده موفق

            try {
                $token = JWTAuth::fromUser($user);
            } catch (JWTException $e) {
                return response()->json(new BaseDto(
                    BaseDtoStatusEnum::ERROR,
                    'مشکلی در ایجاد توکن به وجود آمده است. لطفا دوباره تلاش کنید.'
                ), 500);
            }

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::OK,
                'شماره تلفن با موفقیت بروزرسانی شد.',
                [
                    'token' => $token,
                    'user_data' => $user, // ارسال اطلاعات کاربر
                ]
            ), 200);
        }
    }


    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*?&]/',
        ], [
            'current_password.required' => 'وارد کردن رمز فعلی الزامی است.',
            'new_password.required' => 'وارد کردن رمز جدید الزامی است.',
            'new_password.min' => 'رمز جدید نباید کمتر از 8 کاراکتر باشد.',
            'new_password.confirmed' => 'تایید رمز عبور جدید با رمز عبور مطابقت ندارد.',
            'new_password.regex' => 'رمز جدید باید شامل حروف بزرگ، حروف کوچک، اعداد و نمادها باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رمز عبور رخ داده است.',
                $validator->errors()->toArray()
            ), 400);
        }

        $user = auth()->user();

        // بررسی رمز فعلی
        if (!Hash::check($request->current_password, $user->password)) {
            \Log::info('Entered password: ' . $request->current_password);
            \Log::info('Stored password: ' . $user->password);
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'رمز فعلی نادرست است.'
            ), 400);
        }


        // به‌روزرسانی رمز عبور
        $user->password = Hash::make($request->new_password);
        $user->save();

        $passwordLength = strlen($request->new_password);

        // خارج کردن کاربر از سیستم (در صورت تمایل)
        // Auth::logout();

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'رمز عبور با موفقیت به‌روزرسانی شد.',
            data: [
                'password_length' => $passwordLength // اضافه کردن طول رمز عبور به پاسخ
            ]
        ), 200);
    }
    public function index()
    {
        $user = auth()->user();

        // بررسی اینکه آیا کاربر عکس پروفایل دارد
        $avatarUrl = null; // مقداردهی اولیه به URL عکس پروفایل

        if ($user->has_avatar) {
            $avatar = Image::query()->find($user->image_id);
            $avatarUrl = $avatar ? asset('images/' . $avatar->path) : null;
        }

        // بررسی اینکه کاربر رمز عبور دارد یا نه
        $hasPassword = !is_null($user->password);

        // طول رمز عبور از مقدار ذخیره‌شده در دیتابیس
        $passwordLength = $user->password_length ?? 0;

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            data: [
                'name' => $user->name,
                'phone' => $user->phone,
                'avatar' => $avatarUrl, // فقط در صورت وجود عکس، URL آن ارسال می‌شود
                'has_password' => $hasPassword, // آیا کاربر رمز عبور دارد یا نه
                'password_length' => $passwordLength, // طول رمز عبور
            ]
        ));
    }
    public function setAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'image|mimes:jpeg,png,jpg|max:2048',
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
                422);
        }

        // تنظیم رمز عبور جدید
        $user->password = Hash::make($request->password);
        $user->has_password = true; // در صورتی که رمز عبور قبلاً نداشته باشد، این مقدار به true تغییر می‌کند
        $user->save();

        // محاسبه طول و تعداد کاراکترهای رمز عبور
        $passwordLength = strlen($request->password);

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'رمز عبور با موفقیت ایجاد شد.',
            data: [
                'password_length' => $passwordLength,
            ]
        ), 200);
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
            if ($image) {
                // حذف عکس از سیستم ذخیره‌سازی
                if (Storage::exists($image->path)) {
                    Storage::delete($image->path);
                }

                // حذف رکورد عکس از دیتابیس
                $image->delete();
            }

            // ریست کردن فیلد image_id در جدول کاربران
            $user->image_id = null; // یا مقداری که نشان‌دهنده عکس پیش‌فرض است
            $user->has_avatar = false; // برای نشان دادن عدم وجود آواتار
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
