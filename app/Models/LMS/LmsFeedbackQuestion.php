<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
