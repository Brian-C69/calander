<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarMember extends Model
{
    /** @use HasFactory<\Database\Factories\CalendarMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'calendar_id',
        'user_id',
        'role',
    ];

    public function calendar()
    {
        return $this->belongsTo(Calendar::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
