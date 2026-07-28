<?php

namespace App\Models\Incentive;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Company sengaja tidak disimpan sebagai kolom sendiri — sudah pasti bisa diturunkan
 * dari user->company_id (1 user cuma di 1 company), jadi scoping company dilakukan
 * lewat relasi user, bukan kolom company_id yang bisa nyasar dari data aslinya.
 *
 * @mixin IdeHelperIncentive
 * @property string $id
 * @property string $user_id
 * @property string|null $name
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $incentive_date
 * @property numeric $amount
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 * @method static Builder<static>|Incentive newModelQuery()
 * @method static Builder<static>|Incentive newQuery()
 * @method static Builder<static>|Incentive query()
 * @method static Builder<static>|Incentive whereAmount($value)
 * @method static Builder<static>|Incentive whereCreatedAt($value)
 * @method static Builder<static>|Incentive whereId($value)
 * @method static Builder<static>|Incentive whereIncentiveDate($value)
 * @method static Builder<static>|Incentive whereIsActive($value)
 * @method static Builder<static>|Incentive whereName($value)
 * @method static Builder<static>|Incentive whereNote($value)
 * @method static Builder<static>|Incentive whereUpdatedAt($value)
 * @method static Builder<static>|Incentive whereUserId($value)
 * @mixin \Eloquent
 */
class Incentive extends Model
{
    use HasUlids;

    protected $table = 'incentives';
    protected $fillable = [
        'user_id',
        'name',
        'incentive_date',
        'amount',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'incentive_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        static::addGlobalScope('company', function (Builder $query) {
            $user = Auth::user();

            if ($user && $user->company_id && ! $user->hasRole('Super Admin')) {
                $query->whereHas('user', fn ($q) => $q->where('company_id', $user->company_id));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
