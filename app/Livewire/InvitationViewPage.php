<?php

namespace App\Livewire;

use App\Models\Invitation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Halaman baca-saja undangan untuk yang diundang (karyawan, atasan langsung) dan pembuatnya. */
#[Layout('components.layout', ['title' => 'Undangan'])]
class InvitationViewPage extends Component
{
    public Invitation $record;

    public function mount(Invitation $record): void
    {
        abort_unless(in_array(Auth::id(), [$record->user_id, $record->supervisor_id, $record->created_by], true), 403);

        $this->record = $record->load(['user', 'supervisor']);
    }

    public function render()
    {
        return view('livewire.invitation-view-page');
    }
}
