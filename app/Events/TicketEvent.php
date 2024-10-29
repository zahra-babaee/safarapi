<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $ticketMessage;

    public function __construct(Message $message)
    {
        $this->ticketMessage = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('tickets.' . $this->ticketMessage->ticket_id);
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->ticketMessage->message,
            'ticket_id' => $this->ticketMessage->ticket_id,
            'creator_id' => $this->ticketMessage->creator_id,
            'created_at' => $this->ticketMessage->created_at->toDateTimeString(),
        ];
    }
}
