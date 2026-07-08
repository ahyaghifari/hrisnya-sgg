<?php

declare(strict_types=1);

namespace App\Policies\Master;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Master\Golongans;
use Illuminate\Auth\Access\HandlesAuthorization;

class GolongansPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, Golongans $golongans): bool
    {
        return $authUser->can('View:Golongans');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Golongans');
    }

    public function update(AuthUser $authUser, Golongans $golongans): bool
    {
        return $authUser->can('Update:Golongans');
    }

    public function delete(AuthUser $authUser, Golongans $golongans): bool
    {
        return $authUser->can('Delete:Golongans');
    }

}