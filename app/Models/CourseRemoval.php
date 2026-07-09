<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseRemoval extends Model
{
    use HasFactory;

    protected $table = 'course_removals';
    protected $guarded = [];

    // Currently-removed (excluded) records only.
    public function scopeActive($query)
    {
        return $query->where('status', 'removed');
    }
}
