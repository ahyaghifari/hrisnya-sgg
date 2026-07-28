<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generalisasi dari DoctorFeeItem — `is_manual` dipakai karena banyak item input manual
 * ad-hoc yang tidak link ke katalog komponen (lihat
 * knowledge/markdown/analisis-payroll-2-redesign-struktur.md §2). Kolom `salary_component_id`
 * (dulu link ke katalog lama) dihapus di Fase 1 — katalog freeform sudah diganti
 * PayrollComponentEnum tertutup, tidak ada lagi "link ke baris katalog" buat FeeItem.
 *
 * @mixin IdeHelperFeeItem
 * @property string $id
 * @property string $submission_id
 * @property string|null $user_id
 * @property bool $is_manual
 * @property string $name
 * @property numeric $amount
 * @property bool $is_paid
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Payroll\FeeSubmission $submission
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem whereIsManual($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem whereIsPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem whereSubmissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeItem whereUserId($value)
 * @mixin \Eloquent
 */
class FeeItem extends Model
{
    use HasUlids;

    protected $table = 'fee_items';

    protected $fillable = [
        'submission_id',
        'user_id',
        'is_manual',
        'name',
        'amount',
        'is_paid',
        'notes',
    ];

    protected $casts = [
        'is_manual' => 'boolean',
        'is_paid' => 'boolean',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FeeSubmission::class, 'submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
