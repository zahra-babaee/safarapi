<?php

namespace App\Http\Controllers\v1;

use App\Dto\BaseDto;
use App\Dto\BaseDtoStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    public function index()
    {

    }

    public function store()
    {
        $validator = Validator::make(request()->all(), [
           'title' => 'string|required',
            'priority' => 'in:low,medium,high',
            'description' => 'text|required',
            'creator_id' => 'integer|required|exists:users,id',
            'status' => 'in:open,closed',
        ]);
        if ($validator->fails()) {
            return response()->json(new BaseDto(
                BaseDtoStatusEnum::ERROR,
                'خطاهای اعتبارسنجی رخ داده است.',
                $validator->errors()->toArray()
            ), 400);
        }

        $ticket = Ticket::query()->create([
           'title' => request('title'),
           'description' => request('description'),
           'priority' => request('priority'),
            'creator_id' => request('creator_id'),
            'status' => request('creator_id'),
        ]);
    }

    public function destroy()
    {

    }

    public function update()
    {

    }
}
