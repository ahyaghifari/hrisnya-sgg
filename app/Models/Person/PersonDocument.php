<?php

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lampiran identitas orang (KTP/KK/STR/SIP/RKK), salinan user_profile_documents (lihat
 * PersonSyncService). `file_path` menunjuk file terenkripsi yang SAMA dengan baris asal -
 * jangan dibuka langsung, pembukaan tetap lewat route lampiran lama (dekripsi + cek izin).
 */
class PersonDocument extends Model
{
    use HasUlids;

    protected $table = 'person_documents';

    protected $fillable = [
        'person_id',
        'type',
        'file_path',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}
