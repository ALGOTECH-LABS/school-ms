<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseSession extends Model
{
    use HasFactory;

    protected $table = 'course_sessions';
    protected $guarded = [];

    protected $casts = [
        'session_date' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    // A session is "upcoming" until its end time passes.
    public function getEndsAtAttribute()
    {
        return $this->session_date
            ? $this->session_date->copy()->addMinutes((int) $this->duration_minutes)
            : null;
    }

    public function getIsUpcomingAttribute()
    {
        return $this->status === 'scheduled' && $this->ends_at && $this->ends_at->isFuture();
    }

    public function getIsLiveAttribute()
    {
        return $this->status === 'scheduled'
            && $this->session_date && $this->session_date->isPast()
            && $this->ends_at && $this->ends_at->isFuture();
    }
}
