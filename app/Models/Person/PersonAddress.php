<?php

namespace App\Models\Person;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Alamat orang (1:N per `type`). Salinan dari user_profiles (lihat PersonSyncService). */
class PersonAddress extends Model
{
    use HasUlids;

    protected $table = 'person_addresses';

    protected $fillable = [
        'person_id',
        'type',
        'is_primary',
        'country',
        'province',
        'city',
        'district',
        'village',
        'address',
        'postal_code',
        'rt',
        'rw',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}
