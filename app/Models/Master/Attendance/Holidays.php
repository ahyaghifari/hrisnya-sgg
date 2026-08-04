<?php

namespace App\Models\Master\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Holidays extends Model
{
    use HasUlids;

    protected $table = 'm_holidays';

    protected $fillable = [
        'date',
        'name',
        'is_collective_leave',
    ];

    protected $casts = [
        'date' => 'date',
        'is_collective_leave' => 'boolean',
    ];
}
