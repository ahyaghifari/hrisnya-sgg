<?php

namespace App\Models\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Lampiran bebas milik karyawan sendiri (label ditulis manual, mis. KTP/KK/Ijazah, bukan enum
 * tetap) — beda dari `user_documents` yang isinya surat-surat resmi terbitan admin. Selalu
 * upload sendiri oleh karyawan, banyak baris per user, lihat EmployeeAttendanceStatistics.
 *
 * @property string $id
 * @property string $user_id
 * @property string $label
 * @property string $file_path
 * @property-read string|null $file_url
 */
class UserProfileDocument extends Model
{
    use HasUlids;

    protected $table = 'user_profile_documents';

    protected $fillable = [
        'user_id',
        'label',
        'file_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
