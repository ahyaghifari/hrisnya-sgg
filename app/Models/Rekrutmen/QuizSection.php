<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Subtest dalam satu kuis (tabel quiz_sections milik project `rekrutmen`, DB `recruitment`). */
class QuizSection extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['quiz_id', 'name', 'instructions', 'sort_order'];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }
}
