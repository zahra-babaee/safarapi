<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Message;
use App\Models\TemporaryImage;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;
use App\Events\TicketEvent;
use function Symfony\Component\Translation\t;

class TicketController extends Controller
{
    public function store(Request $request)
    {
        // اعتبارسنجی ورودی‌ها
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'priority' => 'required|in:low,medium,high',
            'description' => 'required|string',
            'attachments' => 'nullable|array|max:3', // حداکثر ۳ فایل مجاز
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf|max:2048' // هر فایل می‌تواند jpg، jpeg، png یا pdf باشد
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, 'فیلدها به درستی پر نشده.',
                data: $validator->errors()), 422);
        }

        // ایجاد تیکت جدید
        $ticket = Ticket::query()->create([
            'title' => $request->title,
            'priority' => $request->priority,
            'description' => $request->description,
            'creator_id' => auth()->id(),
            'status' => 'open',
        ]);

        // ذخیره‌سازی فایل‌ها و ساختن URL‌های ضمیمه
        $attachmentUrls = [];
        if ($request->has('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $imageName = uniqid(time() . '_') . '.' . $file->extension();  // ایجاد یک شناسه منحصر به فرد
                $tempPath = 'attachments/' . $imageName;

                // انتقال فایل به مسیر موقت
                $file->move(public_path('attachments'), $imageName);

                // ایجاد رکورد در جدول ticket_attachments
                $attachment = TicketAttachment::query()->create([
                    'path' => $tempPath,
                    'ticket_id' => $ticket->id
                ]);

                // ذخیره URL کامل در آرایه
                $attachmentUrls[] = url($attachment->path);
            }
        }

        // ذخیره description به عنوان اولین پیام در جدول messages (بدون ضمیمه‌ها)
        Message::query()->create([
            'ticket_id' => $ticket->id,
            'creator_id' => auth()->id(),
            'message' => $request->description,
            'is_read' => false,
            'author' => [
                'name' => $ticket->user ? $ticket->user->name : null,  // نام کاربر
                'avatar' => $ticket->user && $ticket->user->image ? asset($ticket->user->image->path) : null  // عکس پروفایل کاربر
            ]
        ]);

        // بازگرداندن تیکت به همراه لینک‌های ضمیمه در پاسخ نهایی
        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'تیکت با موفقیت ایجاد شد.',
            [
                'ticket' => $ticket,
                'attachments' => $attachmentUrls // ارسال لینک ضمیمه‌ها در پاسخ
            ]
        ), 201);
    }
    /**
     * @OA\Post(
     *     path="/api/v1/tickets",
     *     summary="ایجاد تیکت جدید",
     *     description="این متد یک تیکت جدید ایجاد می‌کند.",
     *     tags={"Tickets"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "priority", "description"},
     *             @OA\Property(property="title", type="string", example="مشکل در ورود به سایت"),
     *             @OA\Property(property="priority", type="string", enum={"low", "medium", "high"}, example="high"),
     *             @OA\Property(property="description", type="string", example="نمیتوانم وارد سایت شوم.")
     *         ),
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="تیکت با موفقیت ایجاد شد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="message", type="string", example="تیکت با موفقیت ایجاد شد."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="مشکل در ورود به سایت"),
     *                 @OA\Property(property="priority", type="string", example="high"),
     *                 @OA\Property(property="status", type="string", example="open"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="خطاهای اعتبارسنجی",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="خطاهای اعتبارسنجی رخ داده است."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/v1/ticket/{id}",
     *     summary="نمایش تیکت خاص",
     *     description="دریافت اطلاعات یک تیکت و پیام‌های مرتبط با آن",
     *     tags={"Tickets"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="شناسه تیکت مورد نظر",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="Authorization",
     *         in="header",
     *         required=true,
     *         description="توکن احراز هویت JWT",
     *         @OA\Schema(type="string", example="Bearer {your_token}")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="اطلاعات تیکت و پیام‌ها با موفقیت بازگردانده شد",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="OK"),
     *             @OA\Property(property="message", type="string", example="تیکت با موفقیت پیدا شد."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="ticket_id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="مشکل در ورود به حساب"),
     *                 @OA\Property(property="priority", type="string", example="high"),
     *                 @OA\Property(property="status", type="string", example="open"),
     *                 @OA\Property(property="creator_id", type="integer", example=2),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-25 14:30"),
     *                 @OA\Property(
     *                     property="messages",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="message_id", type="integer", example=10),
     *                         @OA\Property(property="creator_id", type="integer", example=2),
     *                         @OA\Property(property="message", type="string", example="پیام کاربر یا ادمین"),
     *                         @OA\Property(property="is_read", type="boolean", example=false),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-10-25 15:30")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="کاربر مجاز به مشاهده این تیکت نیست",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="شما دسترسی به این تیکت را ندارید.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="تیکت مورد نظر یافت نشد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ERROR"),
     *             @OA\Property(property="message", type="string", example="تیکت مورد نظر یافت نشد.")
     *         )
     *     )
     * )
     */
    public function ticket($id)
    {
        $ticket = Ticket::with('messages')->find($id);

        if (!$ticket) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'تیکت مورد نظر یافت نشد.'
            ), 404);
        }

        $user = auth()->user();

        // بررسی دسترسی کاربر به تیکت
        if ($user->role !== 'admin' && $ticket->creator_id !== $user->id) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'شما دسترسی به این تیکت را ندارید.'
            ), 403);
        }

        $attachments = TicketAttachment::query()
            ->where('ticket_id', $id)
            ->select('path')
            ->get()
            ->pluck('path') // فقط مسیرهای پیوست‌ها را برمی‌گرداند
            ->map(function ($path) {
                return url($path); // تبدیل به URL کامل
            })
            ->toArray(); // تبدیل به آرایه

        // ساختار پاسخ
        $responseData = [
            'ticket_id' => $ticket->id,
            'title' => $ticket->title,
            'priority' => $ticket->priority,
            'attachments' => $attachments, // آرایه‌ای از URL پیوست‌ها
            'status' => $ticket->status,
            'creator_id' => $ticket->creator_id,
            'created_at' => $ticket->created_at->format('Y-m-d H:i'),
            'messages' => $ticket->messages->map(function ($message) {
                return [
                    'message_id' => $message->id,
                    'message' => $message->message,
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at->format('Y-m-d H:i'),
                    'author' => json_encode([
                        'name' => $message->user ? $message->user->name : null,  // نام کاربر
                        'avatar' => $message->user && $message->user->image ? asset($message->user->image->path) : null  // عکس پروفایل کاربر
                    ])

                ];
            }),
        ];

        return response()->json(new BaseDto(
            BaseDtoStatusEnum::OK,
            'تیکت با موفقیت پیدا شد.',
            $responseData
        ), 200);
    }

    /**
     * @OA\Patch(
     *     path="/tickets/{id}/close",
     *     summary="بستن تیکت",
     *     description="این متد وضعیت تیکت را به «بسته شده» تغییر می‌دهد.",
     *     operationId="markAsClose",
     *     tags={"Tickets"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="شناسه تیکت",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="وضعیت تیکت به «بسته شده» تغییر کرد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="وضعیت تیکت به «بسته شده» تغییر کرد")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="تیکت پیدا نشد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="تیکت مورد نظر یافت نشد")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="عدم دسترسی",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="دسترسی غیرمجاز")
     *         )
     *     )
     * )
     */
    public function markAsClose($id, Request $request)
    {
        // جستجو و دریافت تیکت با id مشخص و متعلق به کاربر فعلی
        $ticket = Ticket::query()
            ->where('creator_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        // تغییر وضعیت تیکت به «بسته شده»
        $ticket->status = 'closed';
        $ticket->save();

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, 'وضعیت تیکت به «بسته شده» تغییر کرد'), 200);
    }
    /**
     * @OA\Get(
     *     path="/api/v1/tickets",
     *     summary="دریافت لیست تیکت‌ها",
     *     description="این متد لیست تیکت‌ها را بر اساس نقش کاربر (مدیر یا کاربر) نمایش می‌دهد.",
     *     tags={"Tickets"},
     *     @OA\Response(
     *         response=200,
     *         description="لیست تیکت‌ها با موفقیت دریافت شد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="مشکل در ورود به سایت"),
     *                 @OA\Property(property="priority", type="string", example="high"),
     *                 @OA\Property(property="status", type="string", example="open"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             ))
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        $user = auth()->user();

        // چک کردن نقش کاربر و دریافت تیکت‌ها بر اساس نقش
        $tickets = Ticket::query()
            ->when($user->role !== 'admin', function ($query) use ($user) {
                $query->where('creator_id', $user->id);
            })
            ->with(['messages' => function($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ticket) {
                return [
                    'ticket_id' => $ticket->id,
                    'title' => $ticket->title,
                    'priority' => $ticket->priority,
                    'description' => $ticket->description,
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at->format('Y-m-d H:i'),
                ];
            });

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data: $tickets), 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tickets/{ticketId}/messages",
     *     summary="ایجاد پیام جدید برای تیکت",
     *     description="این متد پیامی جدید برای یک تیکت خاص ایجاد می‌کند.",
     *     tags={"Tickets"},
     *     @OA\Parameter(
     *         name="ticketId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="شناسه تیکت"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(property="message", type="string", example="مشکل همچنان وجود دارد.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="پیام جدید با موفقیت ایجاد شد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="message", type="string", example="مشکل همچنان وجود دارد."),
     *                 @OA\Property(property="user_role", type="string", example="user"),
     *                 @OA\Property(property="user_name", type="string", example="علی"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="ticket_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="کاربر اجازه دسترسی به این تیکت را ندارد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function storeMessage(Request $request, $ticketId)
    {
        $user = auth()->user();

        // بررسی دسترسی به تیکت
        $ticket = Ticket::query()->findOrFail($ticketId);
        if ($user->role !== 'admin' && $ticket->creator_id !== $user->id) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, 'Unauthorized'), 403);
        }

        // اعتبارسنجی پیام
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, data: $validator->errors()), 422);
        }

        // پاک‌سازی محتوای پیام
        $validatedMessage = Purifier::clean($validator->validated()['message'], function($config) {
            $config->set('HTML.SafeIframe', true);
            $config->set('HTML.Allowed', 'p,b,strong,i,a[href],img[src|alt|width|height]');
            return $config;
        });

        // ایجاد پیام جدید
        $message = Message::query()->create([
            'ticket_id' => $ticketId,
            'creator_id' => $user->id,
            'message' => $validatedMessage,
            'is_read' => false,
        ]);

//        TicketEvent::dispatch($message, $user);

        // ساختن داده‌های ریسپانس
        $response = [
            'message' => $message,
            'user_role' => $user->role,
            'created_at' => $message->created_at->format('Y-m-d H:i'),
            'ticket_id' => $ticketId,
            'author' => [
                'name' => $message->user ? $message->user->name : null,
                'avatar' => $message->user && $message->user->avatar ? asset($message->user->avatar) : null
            ],
        ];

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data: $response), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tickets/{ticketId}/messages",
     *     summary="دریافت پیام‌های یک تیکت",
     *     description="این متد تمام پیام‌های یک تیکت خاص را برمی‌گرداند.",
     *     tags={"Tickets"},
     *     @OA\Parameter(
     *         name="ticketId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="شناسه تیکت"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="پیام‌ها با موفقیت دریافت شد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="ticket_id", type="integer", example=1),
     *                 @OA\Property(property="creator_id", type="integer", example=2),
     *                 @OA\Property(property="creator_role", type="string", example="user"),
     *                 @OA\Property(property="message", type="string", example="مشکل همچنان وجود دارد."),
     *                 @OA\Property(property="is_read", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="کاربر اجازه دسترسی به این تیکت را ندارد",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function getMessages($ticketId)
    {
        $user = auth()->user();

        // دریافت تیکت و اعتبارسنجی دسترسی
        $ticket = Ticket::query()->findOrFail($ticketId);
        if ($user->role !== 'admin' && $ticket->creator_id !== $user->id) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, 'Unauthorized'), 403);
        }

        // دریافت پیام‌ها به همراه نقش نویسنده
        $messages = Message::query()
            ->where('ticket_id', $ticketId)
            ->with([
                'creator' => function ($query) {
                    $query->select('id', 'role');
                }
            ])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'message_id' => $message->id,
                    'ticket_id' => $message->ticket_id,
                    'creator_id' => $message->creator_id,
                    'creator_role' => $message->creator->role ?? null,
                    'message' => $message->message,
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at->format('Y-m-d H:i'),
                    'author' => [
                        'name' => $message->user ? $message->user->name : null,
                        'avatar' => $message->user && $message->user->image ? asset($message->user->image->path) : null
                    ]
                ];
            });

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data: $messages), 200);
    }
}
