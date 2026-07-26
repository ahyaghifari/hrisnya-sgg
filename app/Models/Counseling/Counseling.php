<?php

namespace App\Models\Counseling;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCounseling
 */
class Counseling extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'counselor_id',
        'company_id',
        'issue',
        'response',
        'solution',
        'status',
        'responded_at',
        'responded_by',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    /**
     * Isi keluhan/tanggapan bersifat rahasia — cuma karyawan yang konsul dan konselor yang
     * ditunjuk yang boleh baca, TERMASUK Super Admin tidak boleh (lihat instruksi user
     * 2026-07-26, bukan cuma permission Shield biasa). Dipakai portal & admin Filament.
     */
    public function isVisibleTo(?string $userId): bool
    {
        return $userId !== null && ($userId === $this->user_id || $userId === $this->counselor_id);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
