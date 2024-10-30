<?php

use App\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

//Broadcast::channel('/tickets/{ticketId}', function ($user, $ticketId) {
////    return (int) $user->id === (int) $id;
//    return $user->hasAccessToTicket($ticketId);
//});
//Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//    return (int) $user->id === (int) $id;
//});
//// routes/channels.php

Broadcast::channel('tickets.{ticketId}', function ($user, $ticketId) {
    return (int) $user->id === (int) Ticket::query()->find($ticketId)->creator_id;
});

