<div wire:poll.10s>
<x-page-header title="Kanban" />

<div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <a href="{{ route('kanban.personal') }}" wire:navigate class="btn-primary kanban-toolbar-btn">
        <i class="fa-solid fa-user" style="font-size:12px;"></i> BoardKu
    </a>
    @if ($this->subordinates->isNotEmpty())
        <button type="button" wire:click="openStaffTaskModal" class="btn-primary kanban-toolbar-btn" style="background:#B9308C;">
            <i class="fa-solid fa-user-tag" style="font-size:12px;"></i> Kanban Staff
        </button>
    @endif
    @if ($this->canCreateBoard)
        <a href="{{ route('kanban.create') }}" wire:navigate class="btn-success kanban-toolbar-btn">
            <i class="fa-solid fa-plus" style="font-size:12px;"></i> Board Baru
        </a>
    @endif
</div>

@if ($this->boards->isEmpty() && $this->staffTasks->isEmpty())
    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
        <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada board. {{ $this->canCreateBoard ? 'Buat board baru untuk mulai kelola project.' : 'Minta owner board untuk menambahkanmu sebagai member.' }}</p>
    </div>
@else
    @php
        $statusLabel = fn (string $s) => ['todo' => 'Belum Dikerjakan', 'in_progress' => 'Dikerjakan', 'review' => 'Review', 'done' => 'Selesai'][$s] ?? $s;
    @endphp
    @if ($this->boards->isNotEmpty())
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
            @foreach ($this->boards as $board)
                <a href="{{ route('kanban.show', ['board' => $board]) }}" wire:navigate class="pwa-list" style="display:block;padding:16px;text-decoration:none;border-left:5px solid {{ $board->color }};">
                    <p style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">{{ $board->name }}</p>
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;">{{ $board->description ?: 'Tanpa deskripsi' }}</p>
                    <div style="display:flex;align-items:center;gap:4px;margin-bottom:10px;">
                        @foreach ($board->members as $member)
                            <x-avatar :user="$member->user" :size="24" />
                        @endforeach
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;color:var(--text-muted);">
                        <span><i class="fa-solid fa-list-check" style="font-size:11px;"></i> {{ $board->tasks_count }} task</span>
                        <span>Owner: {{ $board->owner?->name }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($this->staffTasks->isNotEmpty())
        <div style="display:flex;flex-direction:column;gap:14px;{{ $this->boards->isNotEmpty() ? 'margin-top:14px;' : '' }}">
            @foreach ($this->staffTasks as $task)
                <div class="pwa-list" style="display:block;padding:16px;border-left:5px solid #B9308C;border-radius:0;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                        <x-avatar :user="$task->created_by === auth()->id() ? $task->assignedTo : $task->createdBy" :size="24" />
                        <p style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">{{ $task->title }}</p>
                    </div>
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px;">
                        {{ $task->created_by === auth()->id() ? 'Ditugaskan ke ' . $task->assignedTo?->name : 'Ditugaskan oleh ' . $task->createdBy?->name }}
                    </p>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                        @php $authService = app(\App\Services\Kanban\KanbanAuthorizationService::class); @endphp
                        <select wire:change="moveStaffTaskStatus('{{ $task->id }}', $event.target.value)" class="form-input" style="font-size:11px;padding:4px 6px;height:auto;width:auto;" @disabled($task->status === \App\Models\Kanban\KanbanTask::STATUS_DONE)>
                            @foreach (\App\Models\Kanban\KanbanTask::STATUSES as $moveTo)
                                @continue($moveTo !== $task->status && ! $authService->canMoveStatus($task, auth()->user(), $moveTo))
                                <option value="{{ $moveTo }}" @selected($moveTo === $task->status)>{{ $statusLabel($moveTo) }}</option>
                            @endforeach
                        </select>
                        @if ($task->created_by === auth()->id())
                            <button type="button" wire:click="deleteStaffTask('{{ $task->id }}')" wire:confirm="Yakin hapus task ini?" style="color:var(--danger-text);background:none;border:none;cursor:pointer;font-size:12px;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endif

@if ($showStaffTaskModal)
    @php
        $priorityLabel = fn (string $p) => ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'urgent' => 'Mendesak'][$p] ?? $p;
    @endphp
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:50;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click="closeStaffTaskModal">
        <div style="background:var(--surface);border-radius:14px;padding:20px;max-width:480px;width:100%;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Kanban Staff</h3>
            <form wire:submit="createStaffTask" style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label class="form-label">Staff *</label>
                    <select class="form-input" wire:model="staff_id">
                        <option value="">Pilih staff...</option>
                        @foreach ($this->subordinates as $subordinate)
                            <option value="{{ $subordinate->id }}">{{ $subordinate->name }}</option>
                        @endforeach
                    </select>
                    @error('staff_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Judul *</label>
                    <input type="text" class="form-input" wire:model="title">
                    @error('title') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-input" rows="2" wire:model="description"></textarea>
                </div>
                <div class="date-grid">
                    <div>
                        <label class="form-label">Tenggat</label>
                        <input type="date" class="form-input" wire:model="due_date">
                    </div>
                    <div>
                        <label class="form-label">Prioritas</label>
                        <select class="form-input" wire:model="priority">
                            @foreach (\App\Models\Kanban\KanbanTask::PRIORITIES as $p)
                                <option value="{{ $p }}">{{ $priorityLabel($p) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" wire:click="closeStaffTaskModal" class="btn-ghost">Batal</button>
                    <button type="submit" class="btn-primary" style="background:#B9308C;">Tugaskan</button>
                </div>
            </form>
        </div>
    </div>
@endif
</div>
