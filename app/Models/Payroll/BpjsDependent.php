<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tanggungan BPJS Kesehatan per karyawan — 1 baris per karyawan (unique), lihat migration
 * create_bpjs_dependents_table. `count` yang dipakai BpjsKesehatanCalculator, `note` cuma
 * keterangan bebas (mis. "1 ibu, 1 adik").
 *
 * @property string $id
 * @property string $user_id
 * @property int $count
 * @property string|null $note
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BpjsDependent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BpjsDependent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BpjsDependent query()
 */
class BpjsDependent extends Model
{
    use HasUlids;

    protected $table = 'bpjs_dependents';

    protected $fillable = [
        'user_id',
        'count',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
