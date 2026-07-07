<?php

namespace App\Models\Coaching;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperCoaching
 */
class Coaching extends Model
{
    use HasUlids;

    protected $fillable = [
        'company_id',
        'user_id',
        'coach_by',
        'title',
        'goals',
        'employee_condition',
        'solution',
        'improvement_action',
        'improvement_due_date',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coachBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(CoachingProof::class, 'coaching_id')->latest();
    }

    public function getGroupedProofsAttribute()
    {
        $data = $this->proofs
            ->groupBy(function ($proof) {
                return $proof->created_at->format('Y-m-d');
            })
            ->map(function ($items, $date) {

                return $items->groupBy('status')->map(function ($statusItems, $status) use ($date) {
                    $enum = \App\Enum\CoachingStatus::tryFrom($status);

                    return [
                        'date' => $date,
                        'status' => $status,
                        'proofs' => $statusItems,
                    ];
                })->values();
            })
            ->flatten(1)
            ->values();

        return $data;
        dd($data);
    }
}
