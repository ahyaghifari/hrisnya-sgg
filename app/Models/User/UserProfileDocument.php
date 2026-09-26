<?php

namespace App\Models\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Lampiran bebas milik karyawan sendiri (label ditulis manual, mis. KTP/KK/Ijazah, bukan enum
 * tetap) — beda dari `user_documents` yang isinya surat-surat resmi terbitan admin. Selalu
 * upload sendiri oleh karyawan, banyak baris per user, lihat EmployeeStatistics.
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

    /**
     * Bukan URL storage langsung — file lampiran data diri terenkripsi & tersimpan di disk
     * privat, jadi harus lewat route yang decrypt + cek izin (pemilik / permission
     * lihat_lampiran_identitas_karyawan).
     */
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? route('profil-saya.lampiran.show', $this) : null;
    }
}
