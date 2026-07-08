<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Banks;
use Illuminate\Auth\Access\HandlesAuthorization;

class BanksPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, Banks $banks): bool
    {
        return $authUser->can('View:Banks');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Banks');
    }

    public function update(AuthUser $authUser, Banks $banks): bool
    {
        return $authUser->can('Update:Banks');
    }

    public function delete(AuthUser $authUser, Banks $banks): bool
    {
        return $authUser->can('Delete:Banks');
    }

}