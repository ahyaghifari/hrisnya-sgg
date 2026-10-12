<?php

namespace App\Models\SuratTugas;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuratTugasPeserta extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'surat_tugas_peserta';

    protected $fillable = ['surat_tugas_id', 'user_id', 'nama', 'nip', 'jabatan'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Isi snapshot dari data karyawan saat ini (dipanggil saat surat diajukan). */
    public function snapshotFromUser(): void
    {
        $this->loadMissing('user.activeJobPosition.jobPosition');

        $this->forceFill([
            'nama' => $this->user?->name,
            'nip' => $this->user?->nip,
            'jabatan' => $this->user?->activeJobPosition?->jobPosition?->name,
        ])->save();
    }

    // Tampilan di surat: snapshot kalau ada; surat lama (sebelum snapshot) memakai data karyawan saat ini.
    public function displayName(): ?string
    {
        return $this->nama ?? $this->user?->name;
    }

    public function displayNip(): ?string
    {
        return $this->nip ?? $this->user?->nip;
    }

    public function displayJabatan(): ?string
    {
        return $this->jabatan ?? $this->user?->activeJobPosition?->jobPosition?->name;
    }
}
