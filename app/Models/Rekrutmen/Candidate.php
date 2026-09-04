<?php

namespace App\Models\Rekrutmen;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Data diri kandidat (DB rekrutmen_db). Fase 1b: dibuat otomatis lewat
 * JobApplication::booted() saat status -> onboarding/passed. Relasi 9 tabel anak
 * (family/education/dst) belum diport — menyusul di fase yang butuh (detail kandidat).
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

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
