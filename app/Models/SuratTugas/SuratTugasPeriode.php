<?php

namespace App\Models\SuratTugas;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SuratTugasPeriode extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'surat_tugas_periode';

    protected $fillable = ['surat_tugas_id', 'start_date', 'end_date'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];
}
