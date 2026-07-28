<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roster anggota koperasi + cicilan bulanan tetapnya — lihat migration create_user_koperasi_table.
 *
 * @property string $id
 * @property string $user_id
 * @property numeric $amount
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserKoperasi newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserKoperasi newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserKoperasi query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserKoperasi whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserKoperasi whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserKoperasi whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserKoperasi whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserKoperasi whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserKoperasi whereUserId($value)
 * @mixin \Eloquent
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
