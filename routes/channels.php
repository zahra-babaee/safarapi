<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('/tickets/{ticketId}', function ($user, $ticketId) {
//    return (int) $user->id === (int) $id;
    return $user->hasAccessToTicket($ticketId);
});
