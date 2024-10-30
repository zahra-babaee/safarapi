<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $ticketMessage;
    public $creatorName; // Added property for the sender's name

    public function __construct(Message $message, User $user) // Changed to use Message model
    {
        $this->ticketMessage = $message;
        $this->creatorName = $user->name; // Store the sender's name
    }

    public function broadcastOn()
    {
        return new PrivateChannel('tickets.' . $this->ticketMessage->ticket_id);
    }

    public function broadcastAs()
    {
        return 'message';
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->ticketMessage->message,
            'ticket_id' => $this->ticketMessage->ticket_id,
            'creator_id' => $this->ticketMessage->creator_id,
            'creator_name' => $this->creatorName, // Include sender's name
            'created_at' => $this->ticketMessage->created_at->toDateTimeString(),
        ];
    }
}
