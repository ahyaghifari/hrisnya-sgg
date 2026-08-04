<?php

namespace App\Models\LMS;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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
