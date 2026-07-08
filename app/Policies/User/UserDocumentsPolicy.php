<?php

declare(strict_types=1);

namespace App\Policies\User;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\User\UserDocuments;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserDocumentsPolicy
{
    use HandlesAuthorization;
    
    public function view(AuthUser $authUser, UserDocuments $userDocuments): bool
    {
        return $authUser->can('View:UserDocuments');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:UserDocuments');
    }

    public function update(AuthUser $authUser, UserDocuments $userDocuments): bool
    {
        return $authUser->can('Update:UserDocuments');
    }

    public function delete(AuthUser $authUser, UserDocuments $userDocuments): bool
    {
        return $authUser->can('Delete:UserDocuments');
    }

}