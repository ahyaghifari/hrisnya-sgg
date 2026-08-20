<?php

namespace App\Models\Master;

use App\Models\User\UserProfiles;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Religions extends Model
{
    use HasUlids;

    protected $table = "m_religions";

    protected $fillable = [
        'name',
    ];

    public function userProfiles()
    {
        return $this->hasMany(UserProfiles::class, 'religion_id');
    }
}
