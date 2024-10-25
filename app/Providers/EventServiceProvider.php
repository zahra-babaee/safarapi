<?php

namespace App\Providers;

use App\Events\NotificationEvent;
use App\Listeners\SendNotificationListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        NotificationEvent::class => [
            SendNotificationListener::class,
        ],
    ];
}
