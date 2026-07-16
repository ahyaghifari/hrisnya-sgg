<?php

namespace App\Models\Payroll;

use App\Models\Master\Kelas;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTransportAllowance
 */
class TransportAllowance extends Model
{
    use HasUlids;

    protected $table = 'transport_allowances';

    protected $fillable = [
        'kelas_id',
        'amount',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }
}
