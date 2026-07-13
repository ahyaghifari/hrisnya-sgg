<?php

namespace App\Livewire\Kanban;

use App\Models\Kanban\KanbanBoard;
use App\Models\Kanban\KanbanBoardMember;
use App\Services\Kanban\KanbanAuthorizationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CreateKanbanBoard extends Component
{
    public ?string $name = null;
    public ?string $description = null;

    public function mount(): void
    {
        abort_unless(app(KanbanAuthorizationService::class)->canCreateBoard(Auth::user()), 403);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function submit(): void
    {
        $data = $this->validate();
        $user = Auth::user();

        $board = DB::transaction(function () use ($data, $user) {
            $board = KanbanBoard::create([
                'company_id' => $user->company_id,
                'name' => $data['name'],
                'description' => $data['description'],
                'owner_id' => $user->id,
            ]);

            KanbanBoardMember::create([
                'board_id' => $board->id,
                'user_id' => $user->id,
                'role' => KanbanBoardMember::ROLE_OWNER,
            ]);

            return $board;
        });

        Notification::make()
            ->title('Board berhasil dibuat')
            ->success()
            ->send();

        $this->redirect(route('kanban.show', ['board' => $board]), navigate: true);
    }

    public function render()
    {
        return view('livewire.kanban.create-kanban-board')
            ->layout('components.layout', ['title' => 'Board Baru']);
    }
}
