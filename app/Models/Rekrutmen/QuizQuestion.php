<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['quiz_id', 'question', 'image_path', 'options', 'correct_option', 'points', 'sort_order'];

    protected $casts = [
        'options' => 'array',
        'points' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
