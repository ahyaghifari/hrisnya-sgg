<?php

namespace App\Models\Sikartap;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SikartapTestSchedule extends Model
{
    use HasUlids;

    protected $table = 'sikartap_test_schedules';

    protected $fillable = [
        'sikartap_id',
        'tanggal_tes',
        'waktu_tes',
        'tempat_tes',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_tes' => 'date',
        ];
    }

    public function sikartap(): BelongsTo
    {
        return $this->belongsTo(Sikartap::class);
    }
}
