<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $table = 'courses';
    protected $guarded = [];

    public function topics()
    {
        return $this->hasMany(CourseTopic::class, 'course_id')->orderBy('sort_order')->orderBy('id');
    }

    public function lessons()
    {
        return $this->hasMany(CourseLesson::class, 'course_id');
    }

    public function sessions()
    {
        return $this->hasMany(CourseSession::class, 'course_id')->orderBy('session_date');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function class_details()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
