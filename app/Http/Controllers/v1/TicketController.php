<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Message;
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
            $validator = Validator::make($request->all(), [
                'message' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, data: $validator->errors()), 422);
            }

            // پاکسازی محتوای HTML پیام
            $validatedData = $validator->validated();
            $validatedData['message'] = Purifier::clean($validatedData['message'], function($config) {
                $config->set('HTML.SafeIframe', true);
                $config->set('HTML.Allowed', 'p,b,strong,i,a[href],img[src|alt|width|height]'); // اجزای مجاز
                return $config;
            });

            // ذخیره پیام پاکسازی‌شده
            $message = Message::query()->create([
                'ticket_id' => $ticketId,
                'creator_id' => auth()->id(),
                'message' => $validatedData['message'],
                'is_read' => false,
            ]);
            event(new TicketEvent($message));

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data: $message), 201);
        }
        // متد آپلود پیوست برای پیام تیکت

        public function getMessages($ticketId)
        {
            // اعتبارسنجی اینکه کاربر به تیکت دسترسی دارد
            $ticket = Ticket::query()->findOrFail($ticketId);

            if ($ticket->creator_id !== auth()->id()) {
                return response()->json(new BaseDto(BaseDtoStatusEnum::ERROR, 'Unauthorized'), 403);
            }
            // دریافت پیام‌های تیکت
            $messages = Message::query()->where('ticket_id', $ticketId)->orderBy('created_at', 'asc')->get();

            return response()->json(new BaseDto(BaseDtoStatusEnum::OK, data: $messages), 200);
        }
    }
