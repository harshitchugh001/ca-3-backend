<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinkRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'email_record_id',
        'link_click',
        'number_of_times_link_click',
        'open_time',
    ];

    public function emailRecord()
    {
        return $this->belongsTo(EmailRecord::class);
    }
}
