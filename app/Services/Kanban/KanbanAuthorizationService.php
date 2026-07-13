<?php

namespace App\Services\Kanban;

use App\Models\Kanban\KanbanBoard;
use App\Models\Kanban\KanbanBoardMember;
use App\Models\Kanban\KanbanTask;
use App\Models\User;

class KanbanAuthorizationService
{
    public function canCreateBoard(User $user): bool
    {
        return $user->canAccessKaryawanMenu();
    }

    public function canAccessBoard(KanbanBoard $board, User $user): bool
    {
        return $board->members()->where('user_id', $user->id)->exists();
    }

    public function canAddMember(KanbanBoard $board, User $user): bool
    {
        return $this->roleOf($board, $user) === KanbanBoardMember::ROLE_OWNER;
    }

    public function canPromoteToAssigner(KanbanBoard $board, User $user): bool
    {
        return $this->roleOf($board, $user) === KanbanBoardMember::ROLE_OWNER;
    }

    public function canAssignToOthers(KanbanBoard $board, User $user): bool
    {
        return in_array($this->roleOf($board, $user), [
            KanbanBoardMember::ROLE_OWNER,
            KanbanBoardMember::ROLE_ASSIGNER,
        ], true);
    }

    public function canDeleteTask(KanbanTask $task, User $user): bool
    {
        if ($task->created_by === $user->id) {
            return true;
        }

        if (! $task->board_id) {
            return false;
        }

        return $this->roleOf($task->board, $user) === KanbanBoardMember::ROLE_OWNER;
    }

    protected function roleOf(KanbanBoard $board, User $user): ?string
    {
        return $board->members()->where('user_id', $user->id)->value('role');
    }
}
