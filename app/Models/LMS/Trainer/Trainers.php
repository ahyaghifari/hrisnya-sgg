<?php

namespace App\Models\LMS\Trainer;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTrainers
 */
class Trainers extends Model
{
    use HasUlids;
    protected $table = 'trainers';
    protected $fillable = [
        'user_id',
        'type',
        'name',
        'expertise',
        'email',
        'phone',
        'company',
        'address',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
