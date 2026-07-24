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
