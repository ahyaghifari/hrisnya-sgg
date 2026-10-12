<?php

namespace App\Models\SuratTugas;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pemberi tugas (penanda tangan) Surat Perintah Tugas - 1 baris per company. */
class PenandatanganSuratTugas extends Model
{
    use HasUlids;

    protected $table = 'penandatangan_surat_tugas';

    protected $fillable = ['company_id', 'user_id', 'nama', 'jabatan'];

    // Surat yang sudah dibuat menyimpan snapshot sendiri (user/nama/jabatan) - mengubah baris ini
    // hanya berlaku untuk surat berikutnya.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
