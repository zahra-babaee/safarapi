<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;
use App\Events\TicketEvent;

class TicketController extends Controller
{
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
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'priority' => 'required|in:low,medium,high',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, data: $validator->errors()), 422);
        }

        // ایجاد تیکت جدید
        $ticket = Ticket::query()->create([
            'title' => $request->title,
            'priority' => $request->priority,
            'description' => $request->description,
            'creator_id' => auth()->id(),
            'status' => 'open',
        ]);

        // ذخیره description تیکت به عنوان اولین پیام در جدول messages
        Message::query()->create([
            'ticket_id' => $ticket->id,
            'creator_id' => auth()->id(),
            'message' => $request->description,
            'is_read' => false,
        ]);

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data: $ticket), 201);
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
                    'id' => $ticket->id,
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

        TicketEvent::dispatch($message, $user);

        // ساختن داده‌های ریسپانس
        $response = [
            'message' => $message,
            'user_role' => $user->role,
            'user_name' => $user->name,
            'created_at' => $message->created_at->format('Y-m-d H:i'),
            'ticket_id' => $ticketId,
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
            ->with(['creator' => function($query) {
                $query->select('id', 'role');
            }])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'ticket_id' => $message->ticket_id,
                    'creator_id' => $message->creator_id,
                    'creator_role' => $message->creator->role ?? null,
                    'message' => $message->message,
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at->format('Y-m-d H:i'),
                ];
            });

        return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data: $messages), 200);
    }
}
