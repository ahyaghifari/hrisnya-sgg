<?php

namespace App\Services\Sp;

use App\Models\Sp\EmployeeSp;
use Illuminate\Support\Collection;

/**
 * Satu "kasus" = Berita Acara (alur baru) + SP yang lahir darinya, tampil sebagai SATU baris
 * (instruksi user 2026-10-03: BA dan SP jangan dipisah di daftar, cukup keterangan tahap
 * Berita Acara / Keputusan / Surat Peringatan). SP lama tanpa BA alur baru tetap 1 baris SP.
 */
class SpCaseList
{
    /**
     * @param  Collection<int, \App\Models\Sp\SpReport>  $reports  BA alur baru (relasi `sp` & `user` sudah di-load)
     * @param  Collection<int, EmployeeSp>  $sps  SP (relasi `report` & `user` sudah di-load)
     * @return Collection<int, array{report: ?\App\Models\Sp\SpReport, sp: ?EmployeeSp, user: ?\App\Models\User, date: \Illuminate\Support\Carbon}>
     */
    public static function merge(Collection $reports, Collection $sps, ?Collection $invitations = null): Collection
    {
        $rows = $reports->map(fn ($r) => ['report' => $r, 'sp' => $r->sp, 'invitation' => $r->relationLoaded('invitation') ? $r->invitation : null, 'user' => $r->user, 'date' => $r->created_at]);

        // SP yang sudah diwakili baris BA-nya tidak ditampilkan lagi.
        $linked = $reports->pluck('sp_id')->filter()->all();

        $spRows = $sps
            ->reject(fn ($sp) => in_array($sp->id, $linked, true) || $sp->report?->status !== null)
            ->map(fn ($sp) => ['report' => null, 'sp' => $sp, 'invitation' => null, 'user' => $sp->user, 'date' => $sp->start_date]);

        // Undangan yang belum jadi BA tampil sebagai baris sendiri (tahap Undangan).
        $invRows = ($invitations ?? collect())
            ->reject(fn ($i) => $i->report)
            ->map(fn ($i) => ['report' => null, 'sp' => null, 'invitation' => $i, 'user' => $i->user, 'date' => $i->created_at]);

        return $rows->concat($spRows)->concat($invRows)->sortByDesc('date')->values();
    }
}
