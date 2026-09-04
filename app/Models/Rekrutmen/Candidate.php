<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Data diri kandidat (DB rekrutmen_db). Dibuat otomatis lewat JobApplication::booted()
 * saat status -> onboarding/passed. Relasi persetujuan diport (Fase 6); 8 tabel anak lain
 * (family/education/dst) menyusul di fase detail kandidat penuh.
 */
class Candidate extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'recruitment';

    protected $fillable = [
        'user_id', 'job_application_id', 'name', 'nickname', 'nik', 'no_kk',
        'is_onboarded',
    ];

    protected $casts = ['is_onboarded' => 'boolean'];

    public function agreements(): HasMany
    {
        return $this->hasMany(CandidateAgreement::class);
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
