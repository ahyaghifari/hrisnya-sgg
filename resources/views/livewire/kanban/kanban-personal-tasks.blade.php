<div>
@php
    $statusLabel = fn (string $s) => ['todo' => 'Belum Dikerjakan', 'in_progress' => 'Dikerjakan', 'review' => 'Review', 'done' => 'Selesai'][$s] ?? $s;
    $statusColor = fn (string $s) => ['todo' => '#64748b', 'in_progress' => '#1a56c4', 'review' => '#d97706', 'done' => '#047C54'][$s] ?? '#64748b';
    $priorityLabel = fn (string $p) => ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'urgent' => 'Mendesak'][$p] ?? $p;
@endphp

<x-page-header title="Tugas Pribadi" subtitle="Task tanpa board — milikmu sendiri, atau ditugaskan langsung dari atasan" :back="route('kanban')" />

<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
    <button type="button" wire:click="openCreateTask" class="btn-primary">
        <i class="fa-solid fa-plus" style="font-size:12px;"></i> Task Baru
    </button>
</div>

<!-- Sama persis dengan container workspace di board detail: background warna primary, 1 layar penuh,
     header status masuk ke box putih dengan warna beda per status, plus kontrol zoom. -->
<div x-data="{ zoom: 1 }" style="position:relative;">
    <div style="background:var(--brand);border-radius:20px;padding:16px;overflow-y:auto;overflow-x:auto;min-height:100vh;max-height:100vh;" :style="{ zoom: zoom }">
        <div style="display:flex;gap:14px;min-width:min-content;align-items:flex-start;">
        @foreach (\App\Models\Kanban\KanbanTask::STATUSES as $status)
            <div style="flex:0 0 280px;min-width:280px;background:var(--surface);border-radius:12px;overflow:hidden;">
                <div style="background:{{ $statusColor($status) }};padding:10px 12px;display:flex;align-items:center;justify-content:space-between;">
                    <span style="color:#fff;font-weight:700;font-size:13px;">{{ $statusLabel($status) }}</span>
                    <span style="background:rgba(255,255,255,0.25);color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:99px;">{{ count($this->tasksByStatus[$status]) }}</span>
                </div>
                <div style="min-height:80px;padding:8px;display:flex;flex-direction:column;gap:8px;">
                    @forelse ($this->tasksByStatus[$status] as $task)
                        @php ($isStaffTask = $task->created_by !== $task->assigned_to)
                        <div style="background:var(--surface-alt);border:1px solid {{ $isStaffTask ? '#B9308C' : 'var(--border)' }};{{ $isStaffTask ? 'border-left-width:4px;border-radius:0;' : 'border-radius:10px;' }}padding:10px 12px;">
                            @if ($isStaffTask)
                                <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                                    <x-avatar :user="$task->created_by === auth()->id() ? $task->assignedTo : $task->createdBy" :size="18" />
                                    <span style="font-size:10.5px;font-weight:700;color:#B9308C;">
                                        {{ $task->created_by === auth()->id() ? 'Ditugaskan ke ' . $task->assignedTo?->name : 'Ditugaskan oleh ' . $task->createdBy?->name }}
                                    </span>
                                </div>
                            @endif
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:6px;">
                                <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0 0 6px;">{{ $task->title }}</p>
                                <button type="button" wire:click="deleteTask('{{ $task->id }}')" wire:confirm="Yakin hapus task ini?" style="color:var(--danger-text);background:none;border:none;cursor:pointer;font-size:11px;flex-shrink:0;">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                            @if ($task->description)
                                <p style="font-size:12px;color:var(--text-muted);margin:0 0 6px;">{{ $task->description }}</p>
                            @endif
                            <div style="display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:6px;">
                                <span class="badge badge-cancelled">{{ $priorityLabel($task->priority) }}</span>
                                @if ($task->due_date)
                                    <span><i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $task->due_date->translatedFormat('j M') }}</span>
                                @endif
                            </div>
                            <select wire:change="moveTaskStatus('{{ $task->id }}', $event.target.value)" class="form-input" style="font-size:11px;padding:4px 6px;height:auto;">
                                @foreach (\App\Models\Kanban\KanbanTask::STATUSES as $moveTo)
                                    <option value="{{ $moveTo }}" @selected($moveTo === $status)>{{ $statusLabel($moveTo) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @empty
                        <p style="font-size:12px;color:var(--text-muted);text-align:center;padding:12px 0;margin:0;">Kosong</p>
                    @endforelse
                </div>
            </div>
        @endforeach
        </div>
    </div>

    <div style="position:absolute;bottom:16px;right:16px;display:flex;align-items:center;gap:4px;background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);border-radius:99px;padding:6px;z-index:10;">
        <button type="button" @click="zoom = Math.max(0.5, +(zoom - 0.1).toFixed(2))" style="width:26px;height:26px;border-radius:50%;border:none;background:rgba(255,255,255,0.15);color:#fff;cursor:pointer;font-size:12px;">
            <i class="fa-solid fa-minus"></i>
        </button>
        <span x-text="Math.round(zoom * 100) + '%'" style="color:#fff;font-size:11px;font-weight:700;width:38px;text-align:center;"></span>
        <button type="button" @click="zoom = Math.min(1.5, +(zoom + 0.1).toFixed(2))" style="width:26px;height:26px;border-radius:50%;border:none;background:rgba(255,255,255,0.15);color:#fff;cursor:pointer;font-size:12px;">
            <i class="fa-solid fa-plus"></i>
        </button>
        <button type="button" @click="zoom = 1" title="Reset zoom" style="width:26px;height:26px;border-radius:50%;border:none;background:rgba(255,255,255,0.15);color:#fff;cursor:pointer;font-size:11px;">
            <i class="fa-solid fa-arrows-rotate"></i>
        </button>
    </div>
</div>

@if ($showTaskModal)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:50;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click="closeTaskModal">
        <div style="background:var(--surface);border-radius:14px;padding:20px;max-width:480px;width:100%;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Task Pribadi Baru</h3>
            <form wire:submit="createTask" style="display:flex;flex-direction:column;gap:14px;">
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
                    <button type="button" wire:click="closeTaskModal" class="btn-ghost">Batal</button>
                    <button type="submit" class="btn-primary">Buat Task</button>
                </div>
            </form>
        </div>
    </div>
@endif
</div>
