<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lamaran dokter dari portal /dokter project `rekrutmen` (DB recruitment, tabel doctor_applications).
 * Skema milik repo rekrutmen. `data` = isian form, `files` = berkas {key: {path, name, at}} di disk `rekrutmen`.
 */
class DoctorApplication extends Model
{
    protected $connection = 'recruitment';

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array', 'files' => 'array'];

    public const STATUS = [
        'baru' => 'Lamaran diterima',
        'ditinjau' => 'Sedang ditinjau',
        'diterima' => 'Diterima',
        'ditolak' => 'Ditolak',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['diterima', 'ditolak'], true);
    }

    /** Nama + gelar dari isian form. */
    public function namaPelamar(): string
    {
        $d = $this->data ?? [];

        return trim(($d['gelar_depan'] ?? '').' '.($d['nama'] ?? $this->user?->name ?? '-').(($d['gelar_belakang'] ?? '') ? ', '.$d['gelar_belakang'] : ''));
    }

    /** Semua berkas (form + baris repeater) {path => name}; whitelist untuk route unduh. */
    public function allFiles(): array
    {
        $out = [];
        foreach ($this->files ?? [] as $f) {
            $out[$f['path']] = $f['name'];
        }
        foreach ($this->data ?? [] as $rows) {
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (is_array($row) && isset($row['berkas']['path'])) {
                    $out[$row['berkas']['path']] = $row['berkas']['name'];
                }
            }
        }

        return $out;
    }
}
