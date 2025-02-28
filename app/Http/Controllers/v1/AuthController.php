<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Events\UserRegistered;
use App\Models\Image;
use Carbon\Carbon;
use App\Models\Notification;
use Illuminate\Routing\Controller;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use App\Events\NotificationEvent;
use function Symfony\Component\Translation\t;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/v1/api/register",
     *     summary="ثبت‌نام کاربر و ارسال OTP",
     *     description="این متد برای ثبت‌نام کاربر جدید و ارسال کد OTP به شماره تلفن او استفاده می‌شود. شماره تلفن باید اعتبارسنجی شود و زمان بین درخواست‌های OTP کنترل می‌شود.",
     *     tags={"احراز هویت"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="شماره تلفن باید وارد شود و باید با 09 شروع و 11 رقم باشد.",
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 description="شماره تلفن",
     *                 example="09123456789"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="OTP با موفقیت ارسال شد یا درخواست زودهنگام برای OTP.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="کد ارسال شد."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="phone", type="string", example="09123456789"),
     *                 @OA\Property(property="otp_ttl", type="integer", example=120),
     *                 @OA\Property(property="has_account", type="boolean", example=false),
     *                 @OA\Property(property="has_password", type="boolean", example=false),
     *                 @OA\Property(property="otp_request_error", type="string", example="تا 80 ثانیه دیگر نمی‌توانید درخواست جدید بدهید.")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی رخ داده است."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string", example="شماره تلفن نباید خالی باشد."))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="خطای سرور",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطای سرور رخ داده است.")
     *         )
     *     )
     * )
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
        ], [
            'phone.required' => 'شماره تلفن نباید خالی باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                data : $errors
            ), 400);
        }

        $otpValidityDuration = 120; // اعتبار OTP به ثانیه
        $user = User::query()->where('phone', $request->phone)->first();
        $lastOtp = Otp::query()->where('phone', $request->phone)->orderBy('created_at', 'desc')->first();

        // اگر آخرین OTP وجود دارد
        if ($lastOtp) {
            $timeSinceLastOtp = now()->timestamp - $lastOtp->created_at->timestamp;

            if ($timeSinceLastOtp < $otpValidityDuration) {
                $remainingSeconds = $otpValidityDuration - $timeSinceLastOtp;

                return new BaseDto(
                    BaseDtoStatusEnum::ERROR,
                    "تا {$remainingSeconds} ثانیه دیگر نمی‌توانید درخواست جدید بدهید.",
                    data:
                    [
                        'otp_ttl' => $remainingSeconds,
                        'has_account' => (bool)$user,
                        'has_password' => $user ? $user->has_password : false,
                    ],
                );
            }
        }
        $type = 'register';
        $otp = rand(1000, 9999);
        Otp::query()->create([
            'phone' => $request->phone,
            'otp' => $otp,
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes(3),
        ]);


        // ارسال OTP به کاربر
        $this->sendOtp($request->phone, $otp, $type);

        // پیامی برای ارسال OTP جدید
        $message = $lastOtp ? 'کد جدید ارسال شد.' : 'کد ارسال شد.';

        return new BaseDto(
            BaseDtoStatusEnum::OK,
            $message,
            data:
            [
                'phone' => $request->phone,
                'otp_ttl' => $otpValidityDuration,
                'has_account' => (bool)$user,
                'has_password' => $user ? $user->has_password : false,
            ]
        );
    }
    /**
     * @OA\Post(
     *     path="/v1/api/verify-otp",
     *     summary="اعتبارسنجی کد OTP",
     *     description="این متد کد OTP ارسال شده به شماره تلفن را اعتبارسنجی می‌کند. اگر کد صحیح باشد و منقضی نشده باشد، کاربر ایجاد یا بازیابی شده و توکن JWT برای کاربر صادر می‌شود.",
     *     tags={"احراز هویت"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="شماره تلفن باید 11 رقم باشد و کد OTP نیز باید 4 رقم باشد.",
     *         @OA\JsonContent(
     *             required={"phone", "otp"},
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 description="شماره تلفن کاربر",
     *                 example="09123456789"
     *             ),
     *             @OA\Property(
     *                 property="otp",
     *                 type="string",
     *                 description="کد OTP ارسال شده به کاربر",
     *                 example="1234"
     *             ),
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="نام کاربر",
     *                 example="John Doe"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="ورود موفقیت آمیز",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="ورود موفقیت آمیز"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="user Data", type="object",
     *                     @OA\Property(property="phone", type="string", example="09123456789"),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="has_password", type="boolean", example=false),
     *                     @OA\Property(property="has_account", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی رخ داده است."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string", example="شماره تلفن نباید خالی باشد.")),
     *                 @OA\Property(property="otp", type="array", @OA\Items(type="string", example="کدیکبار مصرف را وارد کنید."))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="کد OTP نامعتبر یا منقضی شده",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="کد OTP نامعتبر است یا منقضی شده است.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="خطای سرور در تولید توکن JWT",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="مشکلی در ایجاد توکن به وجود آمده است.")
     *         )
     *     )
     * )
     */
    public function verifyOtp(Request $request)
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
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($otpRecord) {
            // بررسی وجود کاربر با شماره تلفن
            $user = User::query()->where('phone', $request->phone)->first();

            // اگر کاربر وجود نداشت، ایجاد کاربر جدید
            if (!$user) {
                $user = User::query()->create([
                    'phone' => $request->phone,
                    'has_password' => false,
                    'name' => $request->name,
                ]);

                $defaultPhoto = Image::query()->first();
                if ($defaultPhoto) {
                    $user->images()->create(['path' => $defaultPhoto->path]);
                    $user->update(['has_avatar' => true]);
                }
                $user->update(['has_account' => true]);
            }

            // حذف رکورد OTP پس از استفاده
            $otpRecord->delete();

            // تولید توکن JWT برای کاربر
            try {
                $token = JWTAuth::fromUser($user);
            } catch (JWTException $e) {
                return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR,
                    'مشکلی در ایجاد توکن به وجود آمده است.', ),500);
            }

            event(new UserRegistered($user));

            // پاسخ با توکن JWT
            return response()->json(new BaseDto(BaseDtoStatusEnum::OK,
                'ورود موفقیت آمیز',
                data: [
                    'token' => $token,
                    'user Data' => $user,
                ]),200);
        } else {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR,
                'کد OTP نامعتبر است یا منقضی شده است.'),422);
        }
    }
    /**
     * @OA\Post(
     *     path="/v1/api/login",
     *     summary="ورود کاربر با شماره تلفن و رمز عبور",
     *     description="این متد برای ورود کاربر با استفاده از شماره تلفن و رمز عبور او استفاده می‌شود. اعتبارسنجی شماره تلفن و رمز عبور انجام می‌شود.",
     *     tags={"احراز هویت"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="شماره تلفن و رمز عبور باید وارد شوند.",
     *         @OA\JsonContent(
     *             required={"phone", "password"},
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 description="شماره تلفن",
     *                 example="09123456789"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 description="رمز عبور",
     *                 example="password123"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="ورود موفقیت آمیز و دریافت توکن.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="ورود موفقیت آمیز"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string", example="jwt.token.here"),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="phone", type="string", example="09123456789"),
     *                     @OA\Property(property="name", type="string", example="نام کاربر")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی رخ داده است."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string", example="شماره تلفن نباید خالی باشد.")),
     *                 @OA\Property(property="password", type="array", @OA\Items(type="string", example="رمزعبور نباید خالی باشد"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="خطای سرور یا خطا در ایجاد توکن",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="امکان ایجاد توکن وجود ندارد. لطفاً دوباره تلاش کنید.")
     *         )
     *     )
     * )
     */
    public function loginWithPass(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'password' => 'required',
        ], [
            'phone.required' => 'شماره تلفن نباید خالی باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.',
            'password.required' => 'رمزعبور نباید خالی باشد',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();

            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $errors
            ), 400);
        }
        $credentials = $request->only('phone', 'password');
        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR,
                    'نام کاربری یا رمز عبور نادرست است',),400);
            }
        } catch (JWTException $e) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR,
                'امکان ایجاد توکن وجود ندارد. لطفاً دوباره تلاش کنید.'),500);
        }
        $user = auth()->user();

        return response()->json(data: new BaseDto(BaseDtoStatusEnum::OK,'ورود موفقیت آمیز',
            data: [
            'token' => $token,
            'user' => $user,
        ]));
    }
    /**
     * @OA\Post(
     *     path="/v1/api/logout",
     *     summary="خروج کاربر",
     *     description="این متد برای خروج کاربر و باطل کردن توکن JWT استفاده می‌شود.",
     *     tags={"احراز هویت"},
     *
     *     @OA\SecurityScheme(
     *         securityScheme="bearerAuth",
     *         type="http",
     *         scheme="bearer",
     *         bearerFormat="JWT"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="خروج موفقیت آمیز",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="Successfully logged out")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="توکن نامعتبر یا منقضی شده",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="توکن معتبر نیست یا منقضی شده است.")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function logout(Request $request)
    {
        {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK,'Successfully logged out'),200);
        }
    }
    /**
     * @OA\Post(
     *     path="/v1/api/forget-password",
     *     summary="بازیابی رمز عبور با ارسال OTP",
     *     description="این متد برای ارسال کد OTP به شماره تلفن کاربر به منظور بازیابی رمز عبور استفاده می‌شود. شماره تلفن باید معتبر باشد و زمان بین درخواست‌های OTP کنترل می‌شود.",
     *     tags={"احراز هویت"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="شماره تلفن باید وارد شود و باید با 09 شروع و 11 رقم باشد.",
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 description="شماره تلفن کاربر",
     *                 example="09123456789"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *          response=200,
     *          description="OTP با موفقیت ارسال شد یا درخواست زودهنگام برای OTP.",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="string", example="OK"),
     *              @OA\Property(property="message", type="string", example="کد ارسال شد."),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="phone", type="string", example="09123456789"),
     *                  @OA\Property(property="otp_ttl", type="integer", example=120),
     *                  @OA\Property(property="has_account", type="boolean", example=false),
     *                  @OA\Property(property="has_password", type="boolean", example=false),
     *                  @OA\Property(property="otp_request_error", type="string", example="تا 80 ثانیه دیگر نمی‌توانید درخواست جدید بدهید.")
     *              )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی رخ داده است."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string", example="شماره تلفن نباید خالی باشد."))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="کاربری با این شماره تلفن وجود ندارد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="کاربری با این شماره تلفن وجود ندارد.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="خطای سرور",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطای سرور رخ داده است.")
     *         )
     *     )
     * )
     */
    public function forgetPassword(Request $request)
    {
        // اعتبارسنجی شماره تلفن
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
        ], [
            'phone.required' => 'شماره تلفن نباید خالی باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $errors
            ), 400);
        }

        $otpValidityDuration = 120;
        $user = User::query()->where('phone', $request->phone)->first();

        // بررسی وجود کاربر
        if (!$user) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, 'کاربری با این شماره تلفن وجود ندارد.'), 404);
        }

        $lastOtp = Otp::query()
            ->where('phone', $request->phone)
            ->where('type', 'forget')
            ->orderBy('created_at', 'desc')
            ->first();

        // بررسی زمان باقیمانده برای آخرین OTP
        if ($lastOtp) {
            $timeSinceLastOtp = now()->timestamp - $lastOtp->created_at->timestamp;

            if ($timeSinceLastOtp < $otpValidityDuration) {
                $remainingSeconds = $otpValidityDuration - $timeSinceLastOtp;

                return response()->json(new BaseDto(
                    BaseDtoStatusEnum::ERROR,
                    "تا {$remainingSeconds} ثانیه دیگر نمی‌توانید درخواست جدید بدهید.",
                    data: [
                        'otp_ttl' => $remainingSeconds,
                    ]
                ), 429);
            }
        }
        $type = 'forget';

        // ایجاد کد OTP جدید و ذخیره آن
        $otp = rand(1000, 9999);
        Otp::query()->create([
            'phone' => $request->phone,
            'otp' => $otp,
            'type' => $type,
        ]);

        // ارسال OTP به کاربر
        $this->sendOtp($request->phone, $otp ,$type);

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
     *     path="/v1/api/verify-otp-forget",
     *     summary="تایید کد OTP برای بازنشانی رمز عبور",
     *     description="این متد برای تایید کد OTP ارسال‌شده به شماره تلفن کاربر به منظور بازنشانی رمز عبور استفاده می‌شود.",
     *     tags={"احراز هویت"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="شماره تلفن و کد OTP باید وارد شوند.",
     *         @OA\JsonContent(
     *             required={"phone", "otp"},
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 description="شماره تلفن کاربر",
     *                 example="09123456789"
     *             ),
     *             @OA\Property(
     *                 property="otp",
     *                 type="string",
     *                 description="کد OTP ارسال شده به کاربر",
     *                 example="1234"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="کد یکبار مصرف تایید شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="کد یکبار مصرف تایید شد.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی رخ داده است."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string", example="شماره تلفن نباید خالی باشد.")),
     *                 @OA\Property(property="otp", type="array", @OA\Items(type="string", example="کد یکبار مصرف باید 4 رقم باشد."))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="کد OTP نامعتبر است یا منقضی شده است.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="کد OTP نامعتبر است یا منقضی شده است.")
     *         )
     *     )
     * )
     */
    public function verifyOtpForget(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'otp' => 'required|digits:4',
        ], [
            'phone.required' => 'شماره تلفن نباید خالی باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.',
            'otp.required' => 'کد یکبار مصرف را وارد کنید.',
            'otp.digits' => 'کد یکبار مصرف باید 4 رقم باشد.',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $errors
            ), 400);
        }

        // جستجوی کد OTP در دیتابیس
        $otpRecord = Otp::query()
            ->where('phone', $request->phone)
            ->where('otp', $request->otp)
//            ->where('type', 'forget')
            ->where('created_at', '>=', now()->subMinutes(2)) // بررسی زمان معتبر بودن کد
            ->first();

        if (!$otpRecord) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, 'کد OTP نامعتبر است یا منقضی شده است.'), 422);
        }

        // حذف رکورد OTP بعد از تایید موفق
        $otpRecord->delete();

        // تایید موفق کد OTP
        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, 'کد یکبار مصرف تایید شد.'), 200);
    }
    /**
     * @OA\Post(
     *     path="/v1/api/reset-password",
     *     summary="بازنشانی رمز عبور",
     *     description="این متد برای بازنشانی رمز عبور کاربر استفاده می‌شود و نیازمند شماره تلفن و رمز عبور جدید است.",
     *     tags={"احراز هویت"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="شماره تلفن و رمز عبور جدید باید وارد شوند.",
     *         @OA\JsonContent(
     *             required={"phone", "password", "password_confirmation"},
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 description="شماره تلفن کاربر",
     *                 example="09123456789"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 description="رمز عبور جدید کاربر",
     *                 example="NewPassword@123"
     *             ),
     *             @OA\Property(
     *                 property="password_confirmation",
     *                 type="string",
     *                 description="تایید رمز عبور جدید",
     *                 example="NewPassword@123"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="رمز عبور با موفقیت بازنشانی شد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="رمز عبور با موفقیت بازنشانی شد.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="خطاهای اعتبارسنجی",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی رخ داده است."),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string", example="شماره تلفن نباید خالی باشد.")),
     *                 @OA\Property(property="password", type="array", @OA\Items(type="string", example="رمز عبور باید حداقل 8 کاراکتر باشد."))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="کاربری با این شماره تلفن وجود ندارد.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="کاربری با این شماره تلفن وجود ندارد.")
     *         )
     *     )
     * )
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'password' => [
                'required',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/'
            ]
        ], [
            'phone.required' => 'شماره تلفن نباید خالی باشد.',
            'phone.regex' => 'شماره تلفن باید با 09 شروع شود و 11 رقم باشد.',
            'phone.digits' => 'شماره تلفن باید دقیقاً 11 رقم باشد.',
            'password.required' => 'رمز عبور نباید خالی باشد.',
            'password.min' => 'رمز عبور باید حداقل 8 کاراکتر باشد.',
            'password.confirmed' => 'رمز عبور و تأیید آن مطابقت ندارند.',
            'password.regex' => [
                'regex:/[a-z]/' => 'رمز عبور باید حداقل یک حرف کوچک داشته باشد.',
                'regex:/[A-Z]/' => 'رمز عبور باید حداقل یک حرف بزرگ داشته باشد.',
                'regex:/[0-9]/' => 'رمز عبور باید حداقل یک عدد داشته باشد.',
                'regex:/[@$!%*#?&]/' => 'رمز عبور باید حداقل یک کاراکتر خاص (مثل @$!%*#?&) داشته باشد.',
            ]
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $errors
            ), 400);
        }

        // بروزرسانی رمز عبور کاربر
        $user = User::query()->where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, 'کاربری با این شماره تلفن وجود ندارد.'), 404);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, 'رمز عبور با موفقیت بازنشانی شد.'), 200);
    }
//    private function sendOtp($phone, $otp)
        private function sendOtp($phone, $otp, $type)
        {
            switch ($type) {
                case 'register':
                    $message = urlencode("کد فعال‌سازی ثبت‌نام شما: $otp");
                    break;
                case 'forget':
                    $message = urlencode("کد بازیابی رمز عبور شما: $otp");
                    break;
                default:
                    $message = urlencode("کد تأیید شما: $otp");
                    break;
            }
        // ارسال OTP به شماره تلفن
        $curl = curl_init();
        $apikey = '493036347A343565484D3767455769504867546F636A7A30664D6C36316F724E38654E2B42324A2F4166633D';
        $sender = '100090003';
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
