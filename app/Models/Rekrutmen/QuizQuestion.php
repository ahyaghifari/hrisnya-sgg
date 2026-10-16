<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Gambar soal/pilihan ada di disk `rekrutmen_public` (project rekrutmen belum punya
     * storage:link, disk tanpa URL) — dibaca langsung dari disk lalu ditempel sebagai data URI.
     */
    public static function imageUri(?string $path): ?string
    {
        $disk = Storage::disk('rekrutmen_public');

        if (blank($path) || ! $disk->exists($path)) {
            return null;
        }

        return 'data:' . ($disk->mimeType($path) ?: 'image/jpeg') . ';base64,' . base64_encode($disk->get($path));
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
