<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Event\MessageEvent;

class ChatController extends Controller
{
    public function message(Request $request)
    {
        event(new MessageEvent ($request->input('username'), $request->input('message')));
        return [];
    }
}
