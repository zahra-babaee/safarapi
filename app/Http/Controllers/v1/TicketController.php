<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;
use App\Events\TicketEvent;



    class TicketController extends Controller
    {
        // متد ایجاد تیکت جدید
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

            $ticket = Ticket::query()->create([
                'title' => $request->title,
                'priority' => $request->priority,
                'description' => $request->description,
                'creator_id' => auth()->id(),
                'status' => 'open',
            ]);

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data: $ticket ),201);
        }

        // متد ایجاد پیام برای تیکت
        public function storeMessage(Request $request, $ticketId)
        {
            $user = auth()->user(); // دریافت کاربر احراز هویت شده

            $validator = Validator::make($request->all(), [
                'message' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, data: $validator->errors()), 422);
            }

            $validatedData = $validator->validated();
            $validatedData['message'] = Purifier::clean($validatedData['message'], function($config) {
                $config->set('HTML.SafeIframe', true);
                $config->set('HTML.Allowed', 'p,b,strong,i,a[href],img[src|alt|width|height]');
                return $config;
            });

            $message = Message::query()->create([
                'ticket_id' => $ticketId,
                'creator_id' => $user->id,
                'message' => $validatedData['message'],
                'is_read' => false,
            ]);

            TicketEvent::dispatch($message, $user);

            // ساختن داده‌های ریسپانس
            $response = [
                'message' => $message,
                'user_role' => $user->role, // فرض بر این است که رابطه role در مدل User وجود دارد و نام نقش در فیلد name ذخیره شده است
                'user_name' => $user->name,
                'created_at' => $message->created_at,
                'ticket_id' => $ticketId,
            ];

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data: $response), 201);
        }
        // متد آپلود پیوست برای پیام تیکت

        public function getMessages($ticketId)
        {
            // اعتبارسنجی دسترسی کاربر به تیکت
            $ticket = Ticket::query()->findOrFail($ticketId);

            if ($ticket->creator_id !== auth()->id()) {
                return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, 'Unauthorized'), 403);
            }

            // دریافت پیام‌های تیکت همراه با نقش نویسنده
            $messages = Message::query()
                ->where('ticket_id', $ticketId)
                ->with(['creator' => function($query) {
                    $query->select('id', 'role'); // فرض کنیم فیلد role در جدول users وجود دارد
                }])
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'ticket_id' => $message->ticket_id,
                        'creator_id' => $message->creator_id,
                        'creator_role' => $message->creator->role ?? null, // نقش نویسنده پیام
                        'message' => $message->message,
                        'is_read' => $message->is_read,
                        'created_at' => $message->created_at,
                        'updated_at' => $message->updated_at,
                    ];
                });

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data: $messages), 200);
        }
    }
