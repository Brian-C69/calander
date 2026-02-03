<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    protected $fillable = [
        'calendar_id',
        'creator_id',
        'title',
        'description',
        'location',
        'start_at',
        'end_at',
        'is_all_day',
        'recurrence_rule',
        'recurrence_end',
        'visibility',
        'category',
    ];

    protected $casts = [
        'is_all_day' => 'bool',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'recurrence_end' => 'datetime',
    ];

    public function calendar()
    {
        return $this->belongsTo(Calendar::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function attendees()
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function notifications()
    {
        return $this->hasMany(EventNotification::class);
    }
}
