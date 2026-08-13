<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditChanges;
use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Config generik lintas modul (payroll, attendance, dst) — company_id nullable = default
 * global, override per company kalau ada baris company_id spesifik.
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

    /**
     * Key `settings.value` yang dienkripsi (PayrollEncrypted key, sama seperti nominal payroll
     * lain). Selektif per-baris, BUKAN blanket 1 kolom - 60+ key lain (persentase, threshold,
     * string metode) tetap plaintext karena dibaca langsung buat logic kalkulasi
     * (`Setting::resolve()` dipakai di banyak tempat yang butuh baca cepat tanpa expect
     * ciphertext).
     */
    private const ENCRYPTED_KEYS = ['bpjs_declared_value'];

    /**
     * Accessor/mutator kondisional berdasarkan `key` di baris yang sama - hanya baris
     * ENCRYPTED_KEYS yang di-encrypt/decrypt. Butuh `key` sudah ke-set duluan sebelum `value`
     * (urutan array $fillable pas mass-assignment) - dicek aman untuk 2 jalur tulis yang ada
     * (Setting::firstOrCreate/updateOrCreate, `key` selalu bagian dari kondisi pencarian,
     * bukan value yang di-update belakangan).
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: function (?string $value, array $attributes) {
                if ($value === null || ! in_array($attributes['key'] ?? null, self::ENCRYPTED_KEYS, true)) {
                    return $value;
                }

                return (string) app('payroll.encrypter')->decryptString($value);
            },
            set: function (?string $value, array $attributes) {
                if ($value === null || ! in_array($attributes['key'] ?? null, self::ENCRYPTED_KEYS, true)) {
                    return $value;
                }

                return app('payroll.encrypter')->encryptString($value);
            },
        );
    }

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
