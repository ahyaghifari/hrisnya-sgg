<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsMeetingQuizzes
 * @property string $id
 * @property string $meeting_id
 * @property string $quiz_id
 * @property int|null $sort_order
 * @property int|null $duration
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\LmsMeetings $meeting
 * @property-read \App\Models\LMS\LmsQuizzes $quiz
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsQuizResults> $results
 * @property-read int|null $results_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingQuizzes newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingQuizzes newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingQuizzes query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingQuizzes whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingQuizzes whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingQuizzes whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingQuizzes whereMeetingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingQuizzes whereQuizId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingQuizzes whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsMeetingQuizzes whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LmsMeetingQuizzes extends Model
{
    use HasUlids;

    protected $table = 'lms_meeting_quizzes';

    protected $fillable = [
        'meeting_id',
        'quiz_id',
        'sort_order',
        'duration',
        'public_token',
        'is_graduation_requirement',
    ];

    public function meeting()
    {
        return $this->belongsTo(LmsMeetings::class, 'meeting_id');
    }

    public function quiz()
    {
        return $this->belongsTo(LmsQuizzes::class, 'quiz_id');
    }

    public function results()
    {
        return $this->hasMany(LmsQuizResults::class, 'meeting_quiz_id', 'id');
    }

    protected static function booted()
    {
        static::saving(function ($quiz) {
            if (is_null($quiz->sort_order)) {
            $quiz->sort_order = static::max('sort_order') + 1;
            }
        });
    }
}
