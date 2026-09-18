<?php

namespace App\Livewire\MyStaff\Concerns;

use Livewire\Attributes\On;

/**
 * Widget dashboard MyStaff (Ringkasan Tim, Presensi Tim, Cuti Tim, Kontrak/Orientasi Tim,
 * Coaching Tim) - datanya BARU di-query pas tab "Dashboard" diklik pertama kali (instruksi
 * user 2026-09-18: sejak halaman ini jadi 2 tab, tidak perlu collapse per-widget lagi, cukup
 * satu pemicu pas tab-nya dibuka). Semua widget "Dashboard" ikut ke-render di DOM oleh Livewire
 * walau tab-nya belum aktif (Alpine x-show cuma toggle CSS) - guard `$loaded` di sini yang
 * mencegah query jalan sebelum tab Dashboard beneran diklik.
 *
 * Dipicu oleh event browser 'load-my-staff-dashboard' yang di-dispatch Alpine
 * (Livewire.dispatch(...)) sekali doang saat tab Dashboard pertama kali dibuka - lihat
 * my-employees.blade.php.
 */
trait LazyLoadsOnExpand
{
    public bool $loaded = false;

    #[On('load-my-staff-dashboard')]
    public function load(): void
    {
        $this->loaded = true;
    }
}
