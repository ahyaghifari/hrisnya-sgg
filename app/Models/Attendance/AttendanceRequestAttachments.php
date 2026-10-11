<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AttendanceRequestAttachments extends Model
{
    use HasUlids;

    protected $table = 'attendance_req_attachments';

    protected $fillable = [
        'request_id',
        'file_path',
    ];

    /** Batas lampiran per pengajuan lembur. */
    public const MAX_OVERTIME = 5;

    /** Simpan file lampiran pengajuan; gambar jpg/png/webp otomatis diperkecil & dikompres (ImageCompressor). */
    public static function storeUploaded(string $requestId, \Illuminate\Http\UploadedFile $file, string $dir): self
    {
        $compressed = \App\Support\ImageCompressor::compress($file);

        if ($compressed) {
            $path = "{$dir}/{$requestId}/" . \Illuminate\Support\Str::random(40) . '.' . $compressed['extension'];
            Storage::disk('public')->put($path, $compressed['binary']);
        } else {
            $path = Storage::disk('public')->putFile("{$dir}/{$requestId}", $file);
        }

        return self::create(['request_id' => $requestId, 'file_path' => $path]);
    }

    /** Lampiran berupa link: file_path berisi URL http(s) utuh, bukan path di disk (tanpa kolom/migrasi tambahan). */
    public function getIsLinkAttribute(): bool
    {
        return (bool) preg_match('#^https?://#i', $this->file_path ?? '');
    }

    public function getFileUrlAttribute(): ?string
    {
        if ($this->is_link) {
            return $this->file_path;
        }

        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }

    public function getIsImageAttribute(): bool
    {
        return ! $this->is_link && (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $this->file_path ?? '');
    }
}
