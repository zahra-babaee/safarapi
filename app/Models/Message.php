<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'creator_id',
        'message',
        'is_read',
    ];

    // رابطه با مدل Ticket
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    // رابطه با مدل TicketAttachment
    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
