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

    public string $comment = '';

    /** Satu orang satu komentar - unique(announcement_id, user_id) di DB menahan double-submit. */
    public function addComment(): void
    {
        $this->validate(['comment' => ['required', 'string', 'max:1000']], [], ['comment' => 'komentar']);

        $this->record->comments()->firstOrCreate(['user_id' => Auth::id()], ['body' => trim($this->comment)]);
        $this->reset('comment');
    }

    public function render()
    {
        return view('livewire.announcement-detail-page', [
            'comments' => $this->record->comments()->with('user')->oldest()->get(),
        ]);
    }
}
