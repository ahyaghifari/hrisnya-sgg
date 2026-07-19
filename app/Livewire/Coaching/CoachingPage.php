<?php

namespace App\Livewire\Coaching;

use App\Models\Coaching\Coaching;
use App\Services\OrgHierarchyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Coaching" (atasan) — list coaching untuk bawahan (OrgHierarchyService::subordinates())
 * ATAU yang dia sendiri ditunjuk sebagai coach_by. Belum ada form Create/approve bukti —
 * lihat planning/2026-07-18-portal-coaching-counseling.md §4.1.
 */
#[Layout('components.layout', ['title' => 'Coaching'])]
class CoachingPage extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()->can('Create:Coaching'), 403);
    }

    public function render()
    {
        $subordinateIds = app(OrgHierarchyService::class)
            ->subordinates(Auth::user())
            ->pluck('id');

        $coachings = Coaching::whereIn('user_id', $subordinateIds)
            ->orWhere('coach_by', Auth::id())
            ->with(['user', 'coachBy'])
            ->latest()
            ->get();

        return view('livewire.coaching.coaching-page', [
            'coachings' => $coachings,
        ]);
    }
}
