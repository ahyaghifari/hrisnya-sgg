<?php

namespace App\Models\Counseling;

use App\Models\Master\Companies;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCounseling
 * @property string $id
 * @property string $user_id
 * @property string|null $counselor_id
 * @property string $company_id
 * @property string $issue
 * @property string|null $response
 * @property string|null $solution
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $responded_at
 * @property string|null $responded_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Companies $company
 * @property-read User|null $counselor
 * @property-read User|null $respondedBy
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereCounselorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereIssue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereRespondedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereRespondedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereSolution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Counseling whereUserId($value)
 * @mixin \Eloquent
 */
class Counseling extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'counselor_id',
        'company_id',
        'issue',
        'response',
        'solution',
        'status',
        'responded_at',
        'responded_by',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    /**
     * Isi keluhan/tanggapan bersifat rahasia — cuma karyawan yang konsul dan konselor yang
     * ditunjuk yang boleh baca, TERMASUK Super Admin tidak boleh (lihat instruksi user
     * 2026-07-26, bukan cuma permission Shield biasa). Dipakai portal & admin Filament.
     */
    public function isVisibleTo(?string $userId): bool
    {
        return $userId !== null && ($userId === $this->user_id || $userId === $this->counselor_id);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Companies::class, 'company_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
