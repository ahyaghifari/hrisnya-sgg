<?php

declare(strict_types=1);

namespace App\Policies\ApprovalManagement;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ApprovalManagement\MasterApprovalFlows;
use Illuminate\Auth\Access\HandlesAuthorization;

class MasterApprovalFlowsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MasterApprovalFlows');
    }

    public function view(AuthUser $authUser, MasterApprovalFlows $masterApprovalFlows): bool
    {
        return $authUser->can('View:MasterApprovalFlows');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MasterApprovalFlows');
    }

    public function update(AuthUser $authUser, MasterApprovalFlows $masterApprovalFlows): bool
    {
        return $authUser->can('Update:MasterApprovalFlows');
    }

    public function delete(AuthUser $authUser, MasterApprovalFlows $masterApprovalFlows): bool
    {
        return $authUser->can('Delete:MasterApprovalFlows');
    }

    public function restore(AuthUser $authUser, MasterApprovalFlows $masterApprovalFlows): bool
    {
        return $authUser->can('Restore:MasterApprovalFlows');
    }

    public function forceDelete(AuthUser $authUser, MasterApprovalFlows $masterApprovalFlows): bool
    {
        return $authUser->can('ForceDelete:MasterApprovalFlows');
    }

}