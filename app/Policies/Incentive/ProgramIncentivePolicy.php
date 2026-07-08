<?php

declare(strict_types=1);

namespace App\Policies\Incentive;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Incentive\ProgramIncentive;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProgramIncentivePolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, ProgramIncentive $programIncentive): bool
    {
        return $authUser->can('View:ProgramIncentive');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProgramIncentive');
    }

    public function update(AuthUser $authUser, ProgramIncentive $programIncentive): bool
    {
        return $authUser->can('Update:ProgramIncentive');
    }

    public function delete(AuthUser $authUser, ProgramIncentive $programIncentive): bool
    {
        return $authUser->can('Delete:ProgramIncentive');
    }

}