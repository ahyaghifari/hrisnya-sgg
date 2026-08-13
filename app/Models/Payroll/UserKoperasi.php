<?php

namespace App\Models\Payroll;

use App\Casts\PayrollEncrypted;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roster anggota koperasi + cicilan bulanan tetapnya — lihat migration create_user_koperasi_table.
 */
class UserKoperasi extends Model
{
    use HasUlids;

    protected $table = 'user_koperasi';

    protected $fillable = [
        'user_id',
        'amount',
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
        return $this->belongsTo(User::class);
    }
}
