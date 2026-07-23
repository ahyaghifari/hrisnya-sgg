<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Config generik lintas modul (payroll, attendance, dst) — company_id nullable = default
 * global, override per company kalau ada baris company_id spesifik.
 *
 * @mixin IdeHelperSetting
 */
class Setting extends Model
{
    use HasUlids;
    use LogsAuditChanges;

    protected $table = 'settings';

    protected $fillable = [
        'company_id',
        'module',
        'key',
        'type',
        'value',
        'label',
        'description',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    /**
     * Cek company spesifik dulu, fallback ke baris global (company_id NULL), cast sesuai `type`.
     */
    public static function resolve(string $key, ?string $companyId, mixed $default = null): mixed
    {
        $row = static::query()
            ->where('key', $key)
            ->where(fn ($q) => $q->where('company_id', $companyId)->orWhereNull('company_id'))
            ->orderByRaw('(company_id IS NULL) ASC')
            ->first();

        if (! $row) {
            return $default;
        }

        return match ($row->type) {
            'integer' => (int) $row->value,
            'decimal' => (float) $row->value,
            'boolean' => filter_var($row->value, FILTER_VALIDATE_BOOLEAN),
            default => $row->value,
        };
    }

    protected function auditModule(): string
    {
        return $this->module;
    }

    protected function auditCompanyId(): ?string
    {
        return $this->company_id;
    }

    protected function auditKey(): string
    {
        return $this->key;
    }

    protected function auditLabel(): ?string
    {
        return $this->label;
    }

    protected function auditWatchedAttribute(): string
    {
        return 'value';
    }
}
