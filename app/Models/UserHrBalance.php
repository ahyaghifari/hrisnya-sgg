<?php

namespace App\Models;

use App\Enum\Sp\SpLevel;
use App\Models\Sp\EmployeeSp;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cache tampilan widget Karir (planning/2026-09-23-sp-dan-merit-point.md §3.5) - BUKAN sumber
 * kebenaran. Semua validasi/keputusan bisnis WAJIB live-query ke employee_sps/
 * merit_point_transactions, bukan baca model ini.
 */
class UserHrBalance extends Model
{
    use HasUlids;

    const CREATED_AT = null;

    protected $table = 'user_hr_balances';

    protected $fillable = [
        'user_id',
        'merit_point',
        'active_sp_level',
        'active_sp_id',
    ];

    protected $casts = [
        'merit_point' => 'integer',
        'active_sp_level' => SpLevel::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function activeSp(): BelongsTo
    {
        return $this->belongsTo(EmployeeSp::class, 'active_sp_id');
    }
}
