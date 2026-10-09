<?php

namespace App\Livewire;

use App\Models\Invitation;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Widget beranda "Undangan": undangan mendatang (bukan hanya hari ini) untuk yang diundang (karyawan) dan
 * atasan langsungnya. Notifikasi tetap dikirim lebih dulu; widget ini pengingat. Undangan yang dibatalkan,
 * sudah dibuatkan Berita Acara, atau sudah lewat tidak ditampilkan. Kosong = widget tidak tampil.
 */
class InvitationsCard extends Component
{
    public function getInvitationsProperty()
    {
        $userId = Auth::id();

        return Invitation::query()
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('supervisor_id', $userId))
            ->whereNull('cancelled_at')
            ->whereDoesntHave('report')
            ->whereDoesntHave('coaching')
            ->where('scheduled_at', '>=', now())
            ->with('user')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.invitations-card');
    }
}