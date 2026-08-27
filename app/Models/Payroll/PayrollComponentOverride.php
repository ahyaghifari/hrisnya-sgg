<?php

namespace App\Models\Payroll;

use App\Casts\PayrollEncrypted;
use App\Enum\Payroll\PayrollComponentEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override manual per (user, component) - lihat migration create_payroll_component_overrides_table
 * untuk kenapa ini beda dari applyManualRevision()/edited_manually (standing policy vs koreksi
 * 1 payroll). Dicek PayrollService::runComponent() tiap kali Generate/Regenerate.
 */
class PayrollComponentOverride extends Model
{
    use HasUlids;

    protected $table = 'payroll_component_overrides';

    protected $fillable = [
        'user_id',
        'component',
        'amount',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'component' => PayrollComponentEnum::class,
            'is_active' => 'boolean',
            'amount' => PayrollEncrypted::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Override aktif buat (user, component) ini, kalau ada - dipakai PayrollService::runComponent(). */
    public static function activeFor(string $userId, string $component): ?self
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('component', $component)
            ->where('is_active', true)
            ->first();
    }
}
