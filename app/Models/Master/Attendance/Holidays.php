<?php

namespace App\Models\Master\Attendance;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperHolidays
 * @property string $id
 * @property \Illuminate\Support\Carbon $date
 * @property string $name
 * @property bool $is_collective_leave true = cuti bersama (mengurangi jatah cuti tahunan), false = libur nasional biasa
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holidays newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holidays newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holidays query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holidays whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holidays whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holidays whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holidays whereIsCollectiveLeave($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holidays whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Holidays whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
