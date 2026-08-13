<?php

namespace App\Models\Payroll;

use App\Casts\PayrollEncrypted;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roster pendapatan/potongan manual khusus untuk User::PAYROLL_ELIGIBLE_EXECUTIVE_IDS - lihat
 * migration create_executive_allowances_table & ExecutiveAllowanceCalculator.
 */
class ExecutiveAllowance extends Model
{
    use HasUlids;

    protected $table = 'executive_allowances';

    protected $fillable = [
        'user_id',
        'component',
        'type',
        'label',
        'amount',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'amount' => PayrollEncrypted::class,
        ];
    }

    public function user(): BelongsTo
    {
        // withoutGlobalScope('non_karyawan') - baris ini SELALU milik User::
        // PAYROLL_ELIGIBLE_EXECUTIVE_IDS, yang ID-nya masuk NON_KARYAWAN_IDS (dikeluarkan dari
        // User::query() biasa) - tanpa ini relasi user() selalu null, kolom "Penerima" tampil
        // "-" di halaman Tunjangan & Potongan Eksekutif (2026-08-12, sama pola Payroll::user()).
        return $this->belongsTo(User::class)->withoutGlobalScope('non_karyawan');
    }
}
