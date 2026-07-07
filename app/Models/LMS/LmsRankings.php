<?php

namespace App\Models\LMS;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsRankings
 */
class LmsRankings extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'course_id',
        'total_score',
        'rank',
        'calculated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(LmsCourses::class, 'course_id');
    }
}
