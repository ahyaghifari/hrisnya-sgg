<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Companies;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompaniesPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, Companies $companies): bool
    {
        return $authUser->can('View:Companies');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Companies');
    }

    public function update(AuthUser $authUser, Companies $companies): bool
    {
        return $authUser->can('Update:Companies');
    }

    public function delete(AuthUser $authUser, Companies $companies): bool
    {
        return $authUser->can('Delete:Companies');
    }

}