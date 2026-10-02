<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Services\Attendance\AttendanceResyncService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Karyawan sync/hitung ulang presensi SENDIRI (kartu Presensi Hari Ini + tab Kehadiran Profil
 * Saya). Default Auth::user(); user lain hanya lewat parameter $target yang diisi pemanggil
 * setelah memeriksa izin (tab Kehadiran, pemegang view_report_data_absensi - 2026-10-02).
 */
trait ResyncsOwnAttendance
{
    /** Batas rentang tombol "Hitung Ulang Presensi" (hari, inklusif) - cukup buat 1 periode cutoff (30-31 hari). */
    public const RESYNC_MAX_DAYS = 31;

    public function syncMyToday(): void
    {
        // Selalu mulai dari KEMARIN: pembacaan scan batch tidak punya lookback, jadi scan pulang
        // shift malam (tanggal hari ini) kalau kemarin tidak ikut dibaca akan salah dibaca sebagai
        // scan MASUK hari ini. Kemarin ikut dibaca = scan itu dipasangkan ke shift semalam.
        $this->resyncOwn(Carbon::yesterday()->toDateString(), Carbon::today()->toDateString());
    }

    /** Rentang lebih panjang dari batas = tombol disembunyikan di UI, dan ditolak di sini. */
    protected function resyncOwnRange(?string $from, ?string $to, ?User $target = null): void
    {
        abort_unless($from && $to && $from <= $to, 422);
        abort_if(Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1 > self::RESYNC_MAX_DAYS, 422);

        $this->resyncOwn($from, min($to, Carbon::today()->toDateString()), $target);
    }

    /** $target = karyawan lain, hanya diisi pemanggil yang sudah memeriksa izin (lihat
     *  EmployeeStatistics::getCanResyncProperty()); null = diri sendiri. */
    private function resyncOwn(string $from, string $to, ?User $target = null): void
    {
        $user = $target ?? Auth::user();
        $counts = app(AttendanceResyncService::class)->resyncUserRange($user, $from, $to);

        $body = "{$counts['logs']} presensi dibaca ulang dari scan, {$counts['recalculated']} baris jam kerja/telat dihitung ulang.";
        if ($counts['no_nip']) {
            $body .= ' NIP kosong, jadi scan mentah tidak dibaca - hanya hitung ulang baris yang sudah ada.';
        }

        Notification::make()->title('Presensi berhasil dihitung ulang')->body($body)->success()->send();
    }
}
