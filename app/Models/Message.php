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
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'creator_id');  // creator_id به user مربوط است
    }

}
