<?php

namespace App\Events;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $users;

    public function __construct(Notification $notification, array $users)
    {
        $this->notification = $notification;
        $this->users = $users;
    }

    // برای پخش ایونت، می‌توانید متدهای زیر را اضافه کنید
    public function broadcastOn()
    {
        return new Channel('notifications');
    }
}
