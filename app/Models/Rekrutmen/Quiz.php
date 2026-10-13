<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasUlids;

    protected $connection = 'recruitment';

    protected $fillable = ['name', 'duration_minutes', 'instructions'];

    protected $casts = ['duration_minutes' => 'integer'];

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(QuizSection::class)->orderBy('sort_order');
    }

    public function jobOpenings(): BelongsToMany
    {
        return $this->belongsToMany(JobOpening::class, 'job_opening_quiz');
    }
}
