<?php

namespace App\Models;

use App\Models\Master\Companies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    use HasUlids;

    protected $table = 'audit_logs';

    protected $fillable = [
        'company_id',
        'user_id',
        'module',
        'key',
        'label',
        'old_value',
        'new_value',
    ];

    /**
     * Blanket, bukan selektif per-key seperti Setting::value - AuditLog nampung old/new value
     * dari banyak modul (payroll, kepegawaian, dst, lihat LogsAuditChanges/LogsFieldAuditChanges),
     * gak difilter/sort berdasarkan isi kolom ini (cuma `key`/`module`, sort `created_at`), jadi
     * aman dienkripsi semua tanpa refactor SQL. Pakai `encrypted` bawaan Laravel (APP_KEY, bukan
     * key payroll terpisah) karena datanya lintas modul, bukan payroll doang.
     */
    protected $casts = [
        'old_value' => 'encrypted',
        'new_value' => 'encrypted',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Cuma catat perubahan yang beneran dilakukan user lewat UI (Auth::check()) — seeder/console
     * tidak ikut kecatat, biar audit_logs tidak dipenuhi noise `php artisan db:seed`/migrate.
     */
    public static function record(string $module, ?string $companyId, string $key, ?string $label, ?string $oldValue, ?string $newValue): void
    {
        if (! Auth::check()) {
            return;
        }

        static::create([
            'company_id' => $companyId,
            'user_id' => Auth::id(),
            'module' => $module,
            'key' => $key,
            'label' => $label,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    /** Ekstrak alasan dari label berformat "Nama — Konteks (alasan)" - dipakai catatan
     * pembatalan pembayaran payroll (PayrollsTable::cancelPaid, PayrollDetailPage::unmarkAsPaid).
     * Bukan kolom terpisah karena cuma dipakai untuk 1 kasus ini, bukan seluruh AuditLog. */
    public function reasonFromLabel(): ?string
    {
        if (! $this->label || ! str_contains($this->label, '(')) {
            return null;
        }

        return rtrim(substr($this->label, strpos($this->label, '(') + 1), ')');
    }
}
