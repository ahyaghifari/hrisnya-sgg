<?php

declare(strict_types=1);

namespace App\Policies\Shift;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Shift\ShiftSwapRequests;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShiftSwapRequestsPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, ShiftSwapRequests $shiftSwapRequests): bool
    {
        return $authUser->can('View:ShiftSwapRequests');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ShiftSwapRequests');
    }

    public function update(AuthUser $authUser, ShiftSwapRequests $shiftSwapRequests): bool
    {
        return $authUser->can('Update:ShiftSwapRequests');
    }

    public function delete(AuthUser $authUser, ShiftSwapRequests $shiftSwapRequests): bool
    {
        return $authUser->can('Delete:ShiftSwapRequests');
    }

}