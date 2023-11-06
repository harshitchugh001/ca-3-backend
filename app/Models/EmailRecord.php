<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_email',
        'receiver_email',
        'link',
        'read',
        'link_present',
        'token',
        'Email_send_time',
    ];

    public function linkRecords()
    {
        return $this->hasMany(LinkRecord::class);
    }
}
