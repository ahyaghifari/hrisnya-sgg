<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\ExtraShiftAssignment;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExtraShiftAssignmentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ExtraShiftAssignment');
    }

    public function view(AuthUser $authUser, ExtraShiftAssignment $extraShiftAssignment): bool
    {
        return $authUser->can('View:ExtraShiftAssignment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ExtraShiftAssignment');
    }

    public function update(AuthUser $authUser, ExtraShiftAssignment $extraShiftAssignment): bool
    {
        // Baris hasil eksekusi Tukar Shift (source=shift_swap) mengunci angka jadwal yang
        // sudah disetujui lewat alur approval-nya sendiri - tidak boleh diubah siapa pun lewat
        // jalur pengelolaan Extra Shift biasa, termasuk yang punya permission Update.
        if ($extraShiftAssignment->source === 'shift_swap') {
            return false;
        }

        return $authUser->can('Update:ExtraShiftAssignment');
    }

    public function delete(AuthUser $authUser, ExtraShiftAssignment $extraShiftAssignment): bool
    {
        // Sama seperti update() - baris dari Tukar Shift tidak boleh dihapus siapa pun lewat
        // jalur pengelolaan Extra Shift biasa (lihat ShiftSwapRequestService::executeApprovedSwap()).
        if ($extraShiftAssignment->source === 'shift_swap') {
            return false;
        }

        return $authUser->can('Delete:ExtraShiftAssignment');
    }

    public function restore(AuthUser $authUser, ExtraShiftAssignment $extraShiftAssignment): bool
    {
        return $authUser->can('Restore:ExtraShiftAssignment');
    }

    public function forceDelete(AuthUser $authUser, ExtraShiftAssignment $extraShiftAssignment): bool
    {
        return $authUser->can('ForceDelete:ExtraShiftAssignment');
    }

}