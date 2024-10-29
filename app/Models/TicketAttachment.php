<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'message_id',
        'path',
    ];

    // رابطه با مدل Ticket
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    // رابطه با مدل Message
    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
