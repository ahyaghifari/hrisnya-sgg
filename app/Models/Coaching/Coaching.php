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
