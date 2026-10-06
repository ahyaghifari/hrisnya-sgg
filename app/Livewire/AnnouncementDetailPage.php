<?php

namespace App\Livewire;

use App\Models\Announcement;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Detail pengumuman - tujuan klik notifikasi pengumuman. Cuma penerima (atau penerbitnya) yang boleh buka. */
#[Layout('components.layout', ['title' => 'Pengumuman'])]
class AnnouncementDetailPage extends Component
{
    public Announcement $record;

    public function mount(Announcement $record): void
    {
        abort_unless(
            $record->isPublished()
                && ($record->published_by === Auth::id() || $record->recipients()->whereKey(Auth::id())->exists()),
            403,
        );

        $this->record = $record->load('publisher');
    }

    public function render()
    {
        return view('livewire.announcement-detail-page');
    }
}
