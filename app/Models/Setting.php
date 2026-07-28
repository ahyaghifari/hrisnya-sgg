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
 * @property string $id
 * @property string|null $company_id
 * @property string $module
 * @property string $key
 * @property string $type
 * @property string $value
 * @property string $label
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereModule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Setting whereValue($value)
 * @mixin \Eloquent
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
