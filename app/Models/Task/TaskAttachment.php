<?php

namespace App\Models\Task;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskAttachment extends Model
{
    use HasUlids;

    public const TYPE_FILE = 'file';
    public const TYPE_LINK = 'link';

    /** Batas ukuran lampiran PDF (2 MB) - foto dikompres otomatis, PDF tidak, jadi dibatasi. */
    public const PDF_MAX_BYTES = 2 * 1024 * 1024;

    public static function isPdf(\Illuminate\Http\UploadedFile $file): bool
    {
        return $file->getMimeType() === 'application/pdf' || strtolower($file->getClientOriginalExtension()) === 'pdf';
    }

    protected $fillable = [
        'task_id',
        'type',
        'file_path',
        'file_name',
        'description',
        'url',
        'uploaded_by',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isLink(): bool
    {
        return $this->type === self::TYPE_LINK;
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
