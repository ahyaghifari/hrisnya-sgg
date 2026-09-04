<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master override persen kenaikan Gaji Pokok per user (pengganti
 * App\Enum\Payroll\GajiPokokKenaikanOverrideEnum yang hardcode). Dicek
 * GajiPokokCalculator/GajiPokokBreakdownResolver, urutan prioritas: Gaji Khusus
 * (PayrollComponentOverride, skip Calculator total) -> baris aktif di sini -> Setting
 * `gaji_pokok_kenaikan_per_kelas` per company (default 8%).
 */
class GajiPokokKenaikanOverride extends Model
{
    use HasUlids;

    protected $table = 'gaji_pokok_kenaikan_overrides';

    protected $fillable = [
        'user_id',
        'rate',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Rate aktif (%) buat user ini kalau ada, dipakai GajiPokokCalculator/BreakdownResolver. */
    public static function activeRateFor(?string $userId): ?float
    {
        if (! $userId) {
            return null;
        }

        $rate = static::query()->where('user_id', $userId)->where('is_active', true)->value('rate');

        return $rate !== null ? (float) $rate : null;
    }
}
