<?php

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** NIK/KK/NPWP/BPJS + rekening bank. Salinan dari user_profiles (lihat PersonSyncService). */
class PersonIdentity extends Model
{
    use HasUlids;

    protected $table = 'person_identities';

    protected $fillable = [
        'person_id',
        'no_nik',
        'no_kk',
        'npwp',
        'no_bpjs_kes',
        'no_bpjs_ket',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}
