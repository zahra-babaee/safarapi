<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'priority',
        'description',
        'creator_id',
        'status',
    ];

    // رابطه با مدل Message
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    // رابطه با مدل TicketAttachment
    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class);
    }
}
