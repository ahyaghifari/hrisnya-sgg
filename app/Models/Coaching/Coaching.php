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
 * @property string $id
 * @property string $company_id
 * @property string $user_id
 * @property string|null $coach_by
 * @property string|null $title
 * @property string|null $goals
 * @property string|null $employee_condition
 * @property string|null $solution
 * @property string|null $improvement_action
 * @property string|null $improvement_due_date
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $coachBy
 * @property-read Companies $company
 * @property-read mixed $grouped_proofs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Coaching\CoachingProof> $proofs
 * @property-read int|null $proofs_count
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereCoachBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereEmployeeCondition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereGoals($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereImprovementAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereImprovementDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereSolution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Coaching whereUserId($value)
 * @mixin \Eloquent
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

    /**
     * Grouped per tanggal upload saja — bukan lagi per status (status Coaching sendiri yang
     * jadi satu-satunya sumber kebenaran, bukti yang sudah diunggah tidak "berubah status"
     * kalau ditolak, tetap satu kumpulan bukti yang sama, lihat CoachingService::reject()).
     */
    public function getGroupedProofsAttribute()
    {
        return $this->proofs
            ->groupBy(fn ($proof) => $proof->created_at->format('Y-m-d'))
            ->map(fn ($items, $date) => ['date' => $date, 'proofs' => $items])
            ->values();
    }
}
