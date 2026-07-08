<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Pangkats;
use Illuminate\Auth\Access\HandlesAuthorization;

class PangkatsPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, Pangkats $pangkats): bool
    {
        return $authUser->can('View:Pangkats');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Pangkats');
    }

    public function update(AuthUser $authUser, Pangkats $pangkats): bool
    {
        return $authUser->can('Update:Pangkats');
    }

    public function delete(AuthUser $authUser, Pangkats $pangkats): bool
    {
        return $authUser->can('Delete:Pangkats');
    }

}