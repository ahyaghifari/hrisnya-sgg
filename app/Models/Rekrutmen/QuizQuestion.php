<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['quiz_id', 'quiz_section_id', 'question', 'image_path', 'options', 'option_images', 'correct_option', 'max_select', 'points', 'sort_order'];

    protected $casts = [
        'options' => 'array',
        'option_images' => 'array',
        'points' => 'integer',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(QuizSection::class, 'quiz_section_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
