<?php

namespace App\Livewire\Concerns;

/**
 * Tab status rekap: 'approved' (default) atau 'in_review' (masih dalam perjalanan approval).
 * Dipakai halaman Rekap Izin/Cuti/Tukar Shift; tampilannya di partial presensi.partials.approval-tabs.
 */
trait FiltersByApprovalTab
{
    public string $tab = 'approved';

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'in_review' ? 'in_review' : 'approved';

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /** Status attendance_requests yang dicakup tab aktif. */
    protected function tabStatuses(): array
    {
        return $this->tab === 'in_review' ? ['submitted', 'in_review'] : ['approved'];
    }
}
