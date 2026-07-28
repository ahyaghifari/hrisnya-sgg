<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin IdeHelperLmsFeedbackQuestion
 * @property string $id
 * @property string|null $group_label Pengelompokan tampilan, mis. Instruktur/Materi/Fasilitas
 * @property string $question
 * @property int $sort_order
 * @property bool $is_active
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LMS\LmsCourseFeedbackAnswer> $answers
 * @property-read int|null $answers_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsFeedbackQuestion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsFeedbackQuestion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsFeedbackQuestion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsFeedbackQuestion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsFeedbackQuestion whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsFeedbackQuestion whereGroupLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsFeedbackQuestion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsFeedbackQuestion whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsFeedbackQuestion whereQuestion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsFeedbackQuestion whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsFeedbackQuestion whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LmsFeedbackQuestion extends Model
{
    use HasUlids;

    protected $table = 'lms_feedback_questions';

    protected $fillable = [
        'group_label',
        'question',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if ($model->created_by === null && Auth::check()) {
                $model->created_by = Auth::id();
            }

            if (! isset($model->sort_order)) {
                $model->sort_order = (static::max('sort_order') ?? 0) + 1;
            }
        });
    }

    public function answers()
    {
        return $this->hasMany(LmsCourseFeedbackAnswer::class, 'question_id');
    }
}
