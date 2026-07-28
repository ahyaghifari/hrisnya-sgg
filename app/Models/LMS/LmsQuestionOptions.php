<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperLmsQuestionOptions
 * @property string $id
 * @property string $question_id
 * @property string $option_text
 * @property int $is_correct
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LMS\LmsQuestionBanks $question
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionOptions newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionOptions newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionOptions query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionOptions whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionOptions whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionOptions whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionOptions whereOptionText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionOptions whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LmsQuestionOptions whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LmsQuestionOptions extends Model
{
    use HasUlids;

    protected $table = 'lms_question_options';

    protected $fillable = [
        'question_id',
        'option_text',
        'is_correct',
        'order',
    ];

    public function question()
    {
        return $this->belongsTo(LmsQuestionBanks::class, 'question_id');
    }
}
