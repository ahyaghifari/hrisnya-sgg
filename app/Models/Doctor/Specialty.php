<?php

namespace App\Models\Doctor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specialty extends Model
{
    protected $connection = 'doctor';

    protected $guarded = [];

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_specialties')->withPivot('is_primary')->withTimestamps();
    }
}
