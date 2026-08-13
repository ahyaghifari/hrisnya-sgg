<?php

namespace App\Models\Payroll;

use App\Casts\PayrollEncrypted;
use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Kelas;
use App\Models\Master\Pangkats;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Matriks Tunjangan Jabatan LAMA (pangkat x kelas -> nominal), dipakai IncentiveCalculator
 * metode "auto" sebagai pembanding buat hitung selisih penutup transisi. Lihat PositionAllowance
 * (versi baru) untuk struktur padanannya.
 */
class LegacyPositionAllowance extends Model
{
    use HasUlids;
    use LogsAuditChanges;

    protected $table = 'legacy_position_allowances';

    protected $fillable = [
        'pangkat_id',
        'kelas_id',
        'amount',
    ];

    protected $casts = [
        'amount' => PayrollEncrypted::class,
    ];

    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(Pangkats::class, 'pangkat_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    protected function auditModule(): string
    {
        return 'payroll_mapping';
    }

    protected function auditCompanyId(): ?string
    {
        return $this->pangkat?->company_id;
    }

    protected function auditKey(): string
    {
        return 'jabatan_lama:' . $this->pangkat_id . ':' . $this->kelas_id;
    }

    protected function auditLabel(): ?string
    {
        return 'Tunjangan Jabatan Lama — ' . ($this->pangkat?->name ?? '-') . ' / ' . ($this->kelas?->name ?? '-');
    }

    protected function auditWatchedAttribute(): string
    {
        return 'amount';
    }
}
