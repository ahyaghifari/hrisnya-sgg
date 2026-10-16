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

    /** Gambar soal/pilihan dilayani publik oleh app pelamar (`/storage/<path>`), jadi cukup dipanggil via URL-nya. */
    public static function imageUrl(?string $path): ?string
    {
        return blank($path) ? null : rtrim(config('services.rekrutmen.url') ?: 'https://karir.syifaglobalgroup.com', '/') . '/storage/' . ltrim($path, '/');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(QuizSection::class, 'quiz_section_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
