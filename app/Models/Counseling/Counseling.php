<?php

namespace App\Models\Counseling;

use App\Enum\CustomPermissionEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Counseling extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'counselor_id',
        'issue',
        'response',
        'solution',
        'status',
        'is_secret',
        'responded_at',
        'responded_by',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'is_secret' => 'boolean',
            'issue' => 'encrypted',
            'response' => 'encrypted',
            'solution' => 'encrypted',
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
     * Record-nya SENDIRI (baris/halaman detail) - siapa pun yang punya akses modul Coaching &
     * Counseling boleh lihat record ini ADA (status, tanggal, siapa konselornya), TERLEPAS dari
     * is_secret. Yang dibatasi is_secret cuma ISI-nya, lihat isContentVisibleTo().
     */
    public function isAccessibleTo(User $user): bool
    {
        return $user->id === $this->user_id
            || $user->id === $this->counselor_id
            || $user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value);
    }

    /**
     * Isi issue/response/solution — identity match (user_id/counselor_id) SELALU boleh baca
     * terlepas is_secret. Selain itu cuma boleh kalau is_secret=false DAN punya akses modul
     * (instruksi user 2026-08-17, gantiin gerbang biner lama yang cuma identity match).
     */
    public function isContentVisibleTo(User $user): bool
    {
        if ($user->id === $this->user_id || $user->id === $this->counselor_id) {
            return true;
        }

        return ! $this->is_secret && $user->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value);
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
