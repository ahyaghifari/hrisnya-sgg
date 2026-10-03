<?php

namespace App\Models\Sp;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Satu poin Dasar Klarifikasi Berita Acara - nomor dibuat otomatis dari urutan (instruksi user 2026-10-02). */
class SpReportDasarItem extends Model
{
    use HasUlids;

    protected $fillable = ['sp_report_id', 'text', 'sort_order'];
}
