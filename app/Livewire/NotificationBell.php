<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Cuma badge jumlah belum dibaca + link ke /notifikasi (halaman penuh) — bukan dropdown.
 * Dropdown hover (pola .dropdown/.dropdown-menu lain di nav) tidak cocok buat mobile
 * (touch, bukan hover), jadi disamakan: desktop & mobile sama-sama navigasi ke halaman
 * penuh, konsisten dengan pola /akun/notifikasi yang juga halaman penuh, bukan popover.
 */
class NotificationBell extends Component
{
    public string $variant = 'desktop';

    public function getUnreadCountProperty(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}
