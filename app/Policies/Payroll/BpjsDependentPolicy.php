<?php

declare(strict_types=1);

namespace App\Policies\Payroll;

use App\Models\Payroll\BpjsDependent;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * BpjsDependent tidak punya Filament Resource (roster tanggungan cuma dikelola lewat portal,
 * lihat BpjsDependentsPage) jadi permission-nya tidak auto-digenerate `shield:generate` —
 * dibuat manual di sini, sama pola dengan UserKoperasiPolicy.
 */
class BpjsDependentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BpjsDependent');
    }

    public function view(AuthUser $authUser, BpjsDependent $bpjsDependent): bool
    {
        return $authUser->can('View:BpjsDependent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BpjsDependent');
    }

    public function update(AuthUser $authUser, ?BpjsDependent $bpjsDependent = null): bool
    {
        return $authUser->can('Update:BpjsDependent');
    }

    public function delete(AuthUser $authUser, ?BpjsDependent $bpjsDependent = null): bool
    {
        return $authUser->can('Delete:BpjsDependent');
    }
}
