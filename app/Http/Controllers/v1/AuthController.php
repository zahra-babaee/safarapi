<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Models\Image;
use Illuminate\Routing\Controller;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function index()
    {
        //
    }
    /**
     * @OA\Post(
     *     path="/v1/api/register",
     *     summary="Register or login a user with OTP",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="09123456789", description="User's phone number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent or user exists",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="phone", type="string", example="09123456789"),
     *                 @OA\Property(property="has_account", type="boolean", example=true),
     *                 @OA\Property(property="has_password", type="boolean", example=false),
     *                 @OA\Property(property="message", type="string", example="OTP sent."),
     *                 @OA\Property(property="login_method", type="string", example="otp"),
     *                 @OA\Property(property="otp_ttl", type="integer", example=120)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid phone number format")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Please wait 2 minutes before requesting a new OTP."),
     *             @OA\Property(property="otp_ttl", type="integer", example=100)
     *         )
     *     )
     * )
     */
    public function register(Request $request)
    {
        // اعتبارسنجی شماره تلفن
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
        ]);
        if ($validator->fails()) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, $validator->errors()), 400);
        }

        $otpValidityDuration = 120; // اعتبار OTP به ثانیه
        $user = User::query()->where('phone', $request->phone)->first();
        $lastOtp = Otp::query()->where('phone', $request->phone)->orderBy('created_at', 'desc')->first();

        // اگر آخرین OTP وجود دارد
        if ($lastOtp) {
            $timeSinceLastOtp = now()->timestamp - $lastOtp->created_at->timestamp;

            if ($timeSinceLastOtp < $otpValidityDuration) {
                $remainingSeconds = $otpValidityDuration - $timeSinceLastOtp;

                // کاربر تلاش می‌کند دوباره در زمان کمتر از 2 دقیقه درخواست OTP بدهد
                return new BaseDto(
                    BaseDtoStatusEnum::ERROR,
                    "تا {$remainingSeconds} ثانیه دیگر نمی‌توانید درخواست جدید بدهید.",
                    data:
                    [
                        'otp_ttl' => $remainingSeconds,
                        'has_account' => (bool)$user,
                        'has_password' => $user ? $user->has_password : false,
                    ]
                );
            }
        }

        // اگر OTP قبلی منقضی شده یا وجود ندارد، یک OTP جدید تولید کنید
        $otp = rand(1000, 9999);
        Otp::query()->create([
            'phone' => $request->phone,
            'otp' => $otp,
            'type' => 'register',
        ]);

        // ارسال OTP به کاربر
        $this->sendOtp($request->phone, $otp);

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
     *     summary="Verify your phone for Register or login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="09123456789", description="User's phone number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent or user exists",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=200),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="phone", type="string", example="09123456789"),
     *                 @OA\Property(property="has_account", type="boolean", example=true),
     *                 @OA\Property(property="has_password", type="boolean", example=false),
     *                 @OA\Property(property="message", type="string", example="OTP sent."),
     *                 @OA\Property(property="login_method", type="string", example="otp"),
     *                 @OA\Property(property="otp_ttl", type="integer", example=120)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid phone number format")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Please wait 2 minutes before requesting a new OTP."),
     *             @OA\Property(property="otp_ttl", type="integer", example=100)
     *         )
     *     )
     * )
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'otp' => 'required|digits:4',
        ]);
        if($validator->fails()){
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, $validator->errors()), 400);
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
                'name'=> $request->name,
            ]);

            if ($user) {
                $defaultPhoto = Image::query()->first();
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
                return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, data:
                [
                    'message' => 'مشکلی در ایجاد توکن به وجود آمده است.',
                    'status' => '500',
                ]));
            }
            // پاسخ با توکن JWT
            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data: [
                    'token' => $token,
                    'user Data' => $user,
                ]));
        } else {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, data: [
                'message' => 'کد OTP نامعتبر است یا منقضی شده است.',
//                'status' => 422,
            ]));
        }
    }

    public function loginWithPass(Request $request)
    {
        // اعتبارسنجی ورودی‌ها
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'password' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, $validator->errors()), 400);
        }
        $credentials = $request->only('phone', 'password');
        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, data:
                    [
                        'status'=>400,
                        'error' => 'نام کاربری یا رمز عبور نادرست است'
                    ]));
            }
        } catch (JWTException $e) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, data:
                [
                    'message' => 'امکان ایجاد توکن وجود ندارد. لطفاً دوباره تلاش کنید.'
                ]),500);
        }
        $user = auth()->user();

        return response()->json(data: new BaseDto(BaseDtoStatusEnum::OK, data: [
            'token' => $token,
            'user' => $user,
        ]));
    }
    public function logout(Request $request)
    {
        {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data:
                ['message' => 'Successfully logged out']));
        }
    }
    public function forgetPassword(Request $request)
    {
        // اعتبارسنجی ورودی
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
        ]);
        if ($validator->fails()) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, $validator->errors()), 400);
        }

        // بررسی وجود کاربر
        $user = User::query()->where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, data: [
                'message' => 'کاربر با این شماره تلفن وجود ندارد.']
            ),404);
        }
        else{
            $lastOtp = Otp::query()->where('phone', $request->phone)->orderBy('created_at', 'desc')->first();

            if ($lastOtp) {
                $remainingTime = Otp::remainingTime($request->phone, 2);

                if ($remainingTime > 0) {
                    return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR , data:[
//                        'status'=>429,
                        'message' => 'لطفاً قبل از درخواست جدید دو دقیقه صبر کنید.',
                        'otp_ttl' => $remainingTime,
                    ]),429);
                }
        }
            // ایجاد کد OTP و ذخیره آن
            $otp = rand(1000, 9999);
            Otp::query()->create([
                'phone' => $request->phone,
                'otp' => $otp,
                'type' => 'forget',
            ]);

            // ارسال OTP به شماره تلفن کاربر
            $this->sendOtp($request->phone, $otp); // متدی برای ارسال OTP

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data:
                [
                'message' => 'کد یکبار مصرف برای بازنشانی رمز عبور ارسال شد.',
                'otp_ttl' => $remainingTime,
                 ]));
        }
    }
    public function resetPassword(Request $request)
    {
        // اعتبارسنجی ورودی
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'otp' => 'required|digits:4',
            'password' => 'required|min:6|confirmed', // اطمینان از تایید رمز عبور
        ]);
        if ($validator->fails()) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, $validator->errors()), 400);
        }

        // جستجوی کد OTP در دیتابیس
        $otpRecord = Otp::query()
            ->where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('created_at', '>=', now()->subMinutes(2)) // بررسی زمان معتبر بودن کد
            ->first();

        if (!$otpRecord) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, data:
            [
                'message' => 'کد OTP نامعتبر است یا منقضی شده است.'
            ]),422);
        }

        // بروزرسانی رمز عبور کاربر
        $user = User::query()->where('phone', $request->phone)->first();
        $user->update(['password' => Hash::make($request->password)]);

        // حذف رکورد OTP بعد از استفاده
        $otpRecord->delete();

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data:
        ['message' => 'رمز عبور با موفقیت بازنشانی شد.']));
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
