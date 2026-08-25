<?php

namespace App\Models\Sikartap;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SikartapInterviewSchedule extends Model
{
    use HasUlids;

    protected $table = 'sikartap_interview_schedules';

    protected $fillable = [
        'sikartap_id',
        'tanggal_wawancara',
        'waktu_wawancara',
        'tempat_wawancara',
        'pewawancara',
        'pewawancara_2',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_wawancara' => 'date',
        ];
    }

    public function sikartap(): BelongsTo
    {
        return $this->belongsTo(Sikartap::class);
    }
}
