<?php

namespace App\Http\Controllers\v1;

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
     *     path="v1/api/register",
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
        //ورود شماره تلفن
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',

        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }

        $lastOtp = Otp::query()->where('phone', $request->phone)->orderBy('created_at', 'desc')->first();

        if ($lastOtp) {
            $currentTime = now()->timestamp;
            $otpCreatedTime = $lastOtp->created_at->timestamp;
            $timeSinceLastOtp = $currentTime - $otpCreatedTime;
            if ($timeSinceLastOtp < 120) {
                $remainingSeconds = max(0, 120 - $timeSinceLastOtp);

                return response()->json([
                    'message' => 'لطفاً قبل از درخواست جدید 2 دقیقه صبر کنید.',
                    'otp_ttl' => $remainingSeconds, // مقدار TTL به ثانیه
                    'has_account' => false
                ], 429);
            }
        }
        $user = User::query()->where('phone', $request->phone)->first();

        if ($user) {
            // کاربر وجود دارد: بررسی وجود رمز عبور
            if ($user->has_password) {
                return response()->json([
//                    'status' => 200,
//                    'data'=> [
//                        'phone'=>$request->phone,
//                        'has_account' => true,
//                        'has_password' => true,
//                        'message' => 'این کاربر وجود دارد. می‌توانید با رمز عبور هم وارد شوید.',
//                        'login_method' =>'password',
//                        'otp_ttl' => 120, // 120 ثانیه (یا زمان معتبر بودن کد)
//                    ],
                    'phone'=>$request->phone,
                    'message' => 'این کاربر وجود دارد. می‌توانید با رمز عبور هم وارد شوید.',
                    'has_account' => true,
                    'has_password' => true,
                ]);
            } else {
                // کاربر وجود دارد ولی رمز عبور ندارد: ارسال کد OTP
                $EnteredOtp = rand(1000, 9999);
                Otp::query()->create([
                    'phone' => $request->phone,
                    'otp' => $otp,
                ]);
                $this->sendOtp($request->phone, $otp);

                $otpValidityDuration = 120; // اعتبار OTP به ثانیه
                $createdTime = $EnteredOtp->created_at->timestamp; // زمان ایجاد OTP به صورت timestamp
                $currentTime = now()->timestamp; // زمان فعلی به صورت timestamp
                $timeElapsed = $currentTime - $createdTime; // زمان گذشته از ایجاد OTP
                $remainingSeconds = max(0, $otpValidityDuration - $timeElapsed); // زمان باقی‌مانده

                return response()->json([
                    'status' => 200,
                    'data'=> [
                        'phone'=>$request->phone,
                        'has_account' => true,
                        'has_password' =>false,
                        'massage' => 'کدیکبار مصرف ارسال شد.',
                        'login_method' =>'otp',
                        'otp_ttl' => $remainingSeconds, // 120 ثانیه (یا زمان معتبر بودن کد)
                    ],
                ]);
            }
        } else {
            // کاربر وجود ندارد: ارسال کد OTP و ثبت نام
            $otp = rand(1000, 9999);
            Otp::query()->create([
                'phone' => $request->phone,
                'otp' => $otp,
                'type' => 'register',
            ]);
            $this->sendOtp($request->phone, $otp);

            $otpValidityDuration = 120; // اعتبار OTP به ثانیه
            $createdTime = $otp->created_at->timestamp; // زمان ایجاد OTP به صورت timestamp
            $currentTime = now()->timestamp; // زمان فعلی به صورت timestamp
            $timeElapsed = $currentTime - $createdTime; // زمان گذشته از ایجاد OTP
            $remainingSeconds = max(0, $otpValidityDuration - $timeElapsed); // زمان باقی‌مانده

            return response()->json([
                'status' => 200,
                'data'=> [
                    'phone'=>$request->phone,
                    'has_account' => false,
                    'has_password' =>false,
                    'massage' => 'این کاربر وجود ندارد - کد یکبار مصرف برای ثبت نام ارسال شد',
                    'login_method' =>'otp',
                    'otp_ttl' => $remainingSeconds,
                ],
            ]);
        }
    }
    public function verifyOtp(Request $request)
    {
        // اعتبارسنجی ورودی
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
            'otp' => 'required|digits:4',
        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }
        // جستجوی کد OTP در دیتابیس
        $otpRecord = Otp::query()
            ->where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('created_at', '>=', now()->subMinutes(2)) // بررسی زمان معتبر بودن کد
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

                // ارسال پیام ثبت‌نام موفقیت‌آمیز
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
                'data' => [
//                    'data' => $data,
                    'status' => 'success',
                    'token' => $token, // ارسال توکن در پاسخ
                    'user Data' => $user, // ارسال اطلاعات کاربر در پاسخ
                ],
            ]);
        } else {
            return response()->json([
                'message' => 'کد OTP نامعتبر است یا منقضی شده است.',
                'status' => 'error',
            ], 422);
        }
    }

    public function loginWithPass(Request $request)
    {
        $credentials = $request->only('phone', 'password');

        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'Invalid credentials'], 400);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not create token'], 500);
        }

        return response()->json(compact('token'));
    }
    public function logout(Request $request)
    {
        {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json(['message' => 'Successfully logged out']);
        }
    }
//    public function getUser()
//    {
//        try {
//            if (! $user = JWTAuth::parseToken()->authenticate()) {
//                return response()->json(['error' => 'User not found'], 404);
//            }
//        } catch (JWTException $e) {
//            return response()->json(['error' => 'Invalid token'], 400);
//        }
//
//        return response()->json(compact('user'));
//    }

    public function forgetPassword(Request $request)
    {
        // اعتبارسنجی ورودی
        $validator = Validator::make($request->all(), [
            'phone' => 'required|regex:/^09[0-9]{9}$/|digits:11',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        // بررسی وجود کاربر
        $user = User::query()->where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['message' => 'کاربر با این شماره تلفن وجود ندارد.'], 404);
        }
        else{
            $lastOtp = Otp::query()->where('phone', $request->phone)->orderBy('created_at', 'desc')->first();

            if ($lastOtp) {
                $remainingTime = Otp::remainingTime($request->phone, 2); // محاسبه زمان باقی‌مانده

                if ($remainingTime > 0) {
                    return response()->json([
                        'message' => 'لطفاً قبل از درخواست جدید دو دقیقه صبر کنید.',
                        'remaining_time' => $remainingTime, // زمان باقی‌مانده را اضافه کنید
                    ], 429);
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

            return response()->json([
                'message' => 'کد یکبار مصرف برای بازنشانی رمز عبور ارسال شد.',
                'otp_ttl' => 120, // زمان معتبر بودن کد
            ]);
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
            return response()->json($validator->errors()->toJson(), 400);
        }

        // جستجوی کد OTP در دیتابیس
        $otpRecord = Otp::query()
            ->where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('created_at', '>=', now()->subMinutes(2)) // بررسی زمان معتبر بودن کد
            ->first();

        if (!$otpRecord) {
            return response()->json(['message' => 'کد OTP نامعتبر است یا منقضی شده است.'], 422);
        }

        // بروزرسانی رمز عبور کاربر
        $user = User::query()->where('phone', $request->phone)->first();
        $user->update(['password' => Hash::make($request->password)]); // رمز عبور جدید را هش می‌کند

        // حذف رکورد OTP بعد از استفاده
        $otpRecord->delete();

        return response()->json(['message' => 'رمز عبور با موفقیت بازنشانی شد.']);
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
