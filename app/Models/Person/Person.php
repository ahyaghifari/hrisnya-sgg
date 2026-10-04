<?php

namespace App\Models\Person;

use App\Models\Master\Religions;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Salinan identitas orang dari users + user_profiles (tabel lama tetap sumber, lihat
 * App\Services\Identity\PersonSyncService). Jangan diedit langsung dari aplikasi.
 */
class Person extends Model
{
    use HasUlids;

    protected $table = 'persons';

    protected $fillable = [
        'user_id',
        'nama_lengkap',
        'nickname',
        'gender',
        'marital_status',
        'place_of_birth',
        'date_of_birth',
        'religion_id',
        'profile_picture',
        'phone',
        'whatsapp',
        'email_pribadi',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'profile_picture' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes()->withTrashed();
    }

    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religions::class, 'religion_id');
    }

    public function identity(): HasOne
    {
        return $this->hasOne(PersonIdentity::class, 'person_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PersonAddress::class, 'person_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PersonDocument::class, 'person_id');
    }

    public function primaryAddress(): HasOne
    {
        return $this->hasOne(PersonAddress::class, 'person_id')->where('is_primary', true);
    }
}
