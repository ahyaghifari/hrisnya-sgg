<div wire:poll.10s>
@php
    $statusLabel = fn (string $s) => ['todo' => 'Belum Dikerjakan', 'in_progress' => 'Dikerjakan', 'review' => 'Review', 'done' => 'Selesai'][$s] ?? $s;
    $statusBadge = fn (string $s) => ['todo' => 'badge-cancelled', 'in_progress' => 'badge-pending', 'review' => 'badge-pending', 'done' => 'badge-approved'][$s] ?? 'badge-cancelled';
    $statusColor = fn (string $s) => ['todo' => '#64748b', 'in_progress' => '#1a56c4', 'review' => '#d97706', 'done' => '#047C54'][$s] ?? '#64748b';
    $priorityLabel = fn (string $p) => ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'urgent' => 'Mendesak'][$p] ?? $p;
@endphp

<x-page-header :title="$board->name" :subtitle="$board->description" :back="route('kanban')" />

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
        @foreach ($this->members as $member)
            <x-avatar :user="$member->user" :size="32" :title="($member->user?->name ?? '') . ' · ' . ucfirst($member->role)" />
        @endforeach
        @if ($this->canAddMember)
            <button type="button" wire:click="openMemberModal" class="btn-ghost" style="padding:6px 12px;font-size:12px;">
                <i class="fa-solid fa-user-plus" style="font-size:11px;"></i> Kelola
            </button>
        @endif
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
        @if ($this->canAddMember)
            <input type="color" value="{{ $board->color }}" wire:change="updateColor($event.target.value)" title="Ganti warna board" style="width:38px;height:38px;padding:2px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;background:var(--surface);">
        @endif
        @if ($this->canCreateTask)
            <button type="button" wire:click="openCreateTask" class="btn-primary">
                <i class="fa-solid fa-plus" style="font-size:12px;"></i> Task Baru
            </button>
        @endif
    </div>
</div>

<!-- KANBAN WORKSPACE — background gradasi navy (identik warna navigasi desktop), setinggi minimal
     1 layar penuh, 1 container overflow-y:auto + overflow-x:auto supaya kolomnya bisa di-zoom
     tanpa layout pecah. Header tiap kolom masuk ke dalam box putihnya sendiri, warna beda per status.
     Zoom pakai CSS `zoom` (bukan transform:scale) supaya scrollbar/overflow ikut menyesuaikan
     otomatis tanpa perhitungan ukuran manual. -->
<div x-data="{ zoom: 1 }" style="position:relative;">
    <div style="background:{{ $board->color }};border-radius:20px;padding:16px;overflow-y:auto;overflow-x:auto;min-height:100vh;max-height:100vh;" :style="{ zoom: zoom }">
        <div style="display:flex;gap:14px;min-width:min-content;align-items:flex-start;">
        @foreach (\App\Models\Kanban\KanbanTask::STATUSES as $status)
            <div style="flex:0 0 280px;min-width:280px;background:var(--surface);border-radius:12px;overflow:hidden;box-shadow:var(--shadow-card);">
                <div style="background:{{ $statusColor($status) }};padding:10px 12px;display:flex;align-items:center;justify-content:space-between;">
                    <span style="color:#fff;font-weight:700;font-size:13px;">{{ $statusLabel($status) }}</span>
                    <span style="background:rgba(255,255,255,0.25);color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:99px;">{{ count($this->tasksByStatus[$status]) }}</span>
                </div>
                <div style="min-height:80px;padding:8px;display:flex;flex-direction:column;gap:8px;">
                    @forelse ($this->tasksByStatus[$status] as $task)
                        @php ($freeStatus = $this->canChangeStatusFreely($task))
                        @php ($nextStatus = \App\Models\Kanban\KanbanTask::STATUSES[array_search($task->status, \App\Models\Kanban\KanbanTask::STATUSES, true) + 1] ?? null)
                        <div x-data="{ showItemForm: false, showItems: false, showCommentForm: false, showComments: false }" wire:click="openTask('{{ $task->id }}')" style="cursor:pointer;background:var(--surface-alt);{{ $loop->even ? 'filter:brightness(0.95);' : '' }}border:1px solid var(--border);border-radius:10px;padding:10px 12px;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0 0 6px;">{{ $task->title }}</p>
                            <div style="display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--text-muted);">
                                @if ($task->assignedTo)
                                    <x-avatar :user="$task->assignedTo" :size="22" />
                                @else
                                    <span>-</span>
                                @endif
                                @if ($task->due_date)
                                    <span><i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $task->due_date->translatedFormat('j M') }}</span>
                                @endif
                            </div>
                            <div style="margin-top:6px;" onclick="event.stopPropagation()">
                                <select wire:change="moveTaskStatus('{{ $task->id }}', $event.target.value)" class="form-input" style="font-size:11px;padding:4px 6px;height:auto;" @disabled($status === \App\Models\Kanban\KanbanTask::STATUS_DONE || (! $freeStatus && ! $nextStatus))>
                                    <option value="{{ $status }}" selected>{{ $statusLabel($status) }}</option>
                                    @if ($freeStatus)
                                        @foreach (\App\Models\Kanban\KanbanTask::STATUSES as $moveTo)
                                            @continue($moveTo === $status)
                                            <option value="{{ $moveTo }}">{{ $statusLabel($moveTo) }}</option>
                                        @endforeach
                                    @elseif ($nextStatus)
                                        <option value="{{ $nextStatus }}">{{ $statusLabel($nextStatus) }}</option>
                                    @endif
                                </select>
                            </div>

                            <!-- Checklist — disembunyikan total kalau task sudah selesai (terkunci, cuma bisa dikomentari) -->
                            @if ($task->status !== \App\Models\Kanban\KanbanTask::STATUS_DONE)
                            <div style="margin-top:6px;" onclick="event.stopPropagation()">
                                <button type="button" @click="showItems = !showItems" class="btn-ghost" style="font-size:11px;padding:3px 8px;">
                                    <i class="fa-solid fa-chevron-down" :class="{ 'fa-rotate-180': showItems }" style="font-size:9px;"></i>
                                    Checklist ({{ $task->items->where('is_done', true)->count() }}/{{ $task->items->count() }})
                                </button>
                                <div x-show="showItems" x-cloak style="display:flex;flex-direction:column;gap:4px;margin-top:6px;max-height:130px;overflow-y:auto;background:rgba(0,0,0,0.18);border-radius:8px;padding:6px 8px;">
                                    @forelse ($task->items as $item)
                                        <div style="display:flex;align-items:center;gap:6px;font-size:11px;background:var(--surface-alt);border-radius:6px;padding:4px 6px;">
                                            <input type="checkbox" wire:click="toggleItem('{{ $item->id }}')" @checked($item->is_done) style="accent-color:#0f766e;width:15px;height:15px;border-radius:4px;cursor:pointer;flex-shrink:0;">
                                            <span style="flex:1;{{ $item->is_done ? 'text-decoration:line-through;color:var(--text-muted);' : '' }}">{{ $item->title }}</span>
                                            <button type="button" wire:click="deleteItem('{{ $item->id }}')" style="color:var(--danger-text);background:none;border:none;cursor:pointer;padding:0;line-height:1;">
                                                <i class="fa-solid fa-trash" style="font-size:10px;"></i>
                                            </button>
                                        </div>
                                    @empty
                                        <p style="font-size:11px;color:var(--text-muted);margin:0;">Belum ada item.</p>
                                    @endforelse
                                </div>
                                @if ($this->canManageItems($task))
                                <button type="button" @click="showItemForm = !showItemForm" class="btn-ghost" style="font-size:11px;padding:3px 8px;margin-top:4px;">
                                    <i class="fa-solid fa-plus" style="font-size:9px;"></i> Item
                                </button>
                                <div x-show="showItemForm" x-cloak style="display:flex;gap:6px;margin-top:6px;">
                                    <input type="text" x-ref="itemInput" class="form-input" style="font-size:11px;padding:4px 6px;height:auto;" placeholder="Judul item..." @keydown.enter="$wire.addItemToTask('{{ $task->id }}', $refs.itemInput.value); $refs.itemInput.value = ''; showItemForm = false; showItems = true">
                                    <button type="button" class="btn-primary" style="font-size:11px;padding:4px 10px;white-space:nowrap;" @click="$wire.addItemToTask('{{ $task->id }}', $refs.itemInput.value); $refs.itemInput.value = ''; showItemForm = false; showItems = true">Simpan</button>
                                </div>
                                @endif
                            </div>
                            @endif

                            <!-- Komentar -->
                            <div style="margin-top:6px;" onclick="event.stopPropagation()">
                                <button type="button" @click="showComments = !showComments" class="btn-ghost" style="font-size:11px;padding:3px 8px;">
                                    <i class="fa-solid fa-chevron-down" :class="{ 'fa-rotate-180': showComments }" style="font-size:9px;"></i>
                                    <i class="fa-solid fa-comment" style="font-size:10px;"></i> {{ $task->comments->count() }}
                                </button>
                                <div x-show="showComments" x-cloak style="display:flex;flex-direction:column;gap:4px;margin-top:6px;max-height:130px;overflow-y:auto;background:rgba(0,0,0,0.18);border-radius:8px;padding:6px 8px;">
                                    @forelse ($task->comments as $comment)
                                        <div style="font-size:11px;background:var(--surface-alt);border-radius:6px;padding:5px 7px;">
                                            <p style="margin:0 0 2px;font-weight:600;color:var(--text-heading);">{{ $comment->user?->name }}</p>
                                            <p style="margin:0;color:var(--text-body);">{{ $comment->comment }}</p>
                                        </div>
                                    @empty
                                        <p style="font-size:11px;color:var(--text-muted);margin:0;">Belum ada komentar.</p>
                                    @endforelse
                                </div>
                                <button type="button" @click="showCommentForm = !showCommentForm" class="btn-ghost" style="font-size:11px;padding:3px 8px;margin-top:4px;">
                                    <i class="fa-solid fa-plus" style="font-size:9px;"></i> Komentari
                                </button>
                                <div x-show="showCommentForm" x-cloak style="display:flex;gap:6px;margin-top:6px;">
                                    <input type="text" x-ref="commentInput" class="form-input" style="font-size:11px;padding:4px 6px;height:auto;" placeholder="Tulis komentar..." @keydown.enter="$wire.addCommentToTask('{{ $task->id }}', $refs.commentInput.value); $refs.commentInput.value = ''; showCommentForm = false; showComments = true">
                                    <button type="button" class="btn-primary" style="font-size:11px;padding:4px 10px;white-space:nowrap;" @click="$wire.addCommentToTask('{{ $task->id }}', $refs.commentInput.value); $refs.commentInput.value = ''; showCommentForm = false; showComments = true">Simpan</button>
                                </div>
                            </div>
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

<!-- MODAL: TASK BARU -->
@if ($showTaskModal)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:50;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click="closeTaskModal">
        <div style="background:var(--surface);border-radius:14px;padding:20px;max-width:480px;width:100%;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Task Baru</h3>
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
                <div>
                    <label class="form-label">Assign ke (bisa lebih dari satu) *</label>
                    <div style="display:flex;flex-direction:column;gap:6px;max-height:160px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px 10px;">
                        @forelse ($this->assignableMembers as $member)
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-body);">
                                <input type="checkbox" wire:model="assignedUsers" value="{{ $member->user_id }}">
                                {{ $member->user?->name }}
                            </label>
                        @empty
                            <p style="font-size:12px;color:var(--text-muted);margin:0;">Belum ada member lain di board ini.</p>
                        @endforelse
                    </div>
                    @error('assignedUsers') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" wire:click="closeTaskModal" class="btn-ghost">Batal</button>
                    <button type="submit" class="btn-primary">Buat Task</button>
                </div>
            </form>
        </div>
    </div>
@endif

<!-- MODAL: DETAIL TASK -->
@if ($this->selectedTask)
    @php($task = $this->selectedTask)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:50;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click="closeTask">
        <div style="background:var(--surface);border-radius:14px;padding:20px;max-width:520px;width:100%;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">{{ $task->title }}</h3>
                <button type="button" wire:click="closeTask" class="btn-ghost" style="padding:4px 8px;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div style="display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap;">
                <span class="badge {{ $statusBadge($task->status) }}">{{ $statusLabel($task->status) }}</span>
                <span class="badge badge-cancelled">{{ $priorityLabel($task->priority) }}</span>
            </div>

            @if ($task->description)
                <p style="font-size:13px;color:var(--text-body);margin:0 0 12px;">{{ $task->description }}</p>
            @endif

            <div style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">
                <p style="margin:0 0 4px;">Dibuat oleh: {{ $task->createdBy?->name }}</p>
                <p style="margin:0 0 4px;">Ditugaskan ke: {{ $task->members->pluck('user.name')->filter()->implode(', ') ?: '-' }}</p>
                @if ($task->due_date)
                    <p style="margin:0;">Tenggat: {{ $task->due_date->translatedFormat('j F Y') }}</p>
                @endif
            </div>

            <!-- Lampiran -->
            <div style="margin-bottom:14px;">
                <p class="form-label" style="margin-bottom:6px;">Lampiran</p>
                <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px;">
                    @forelse ($task->attachments as $attachment)
                        <div style="display:flex;align-items:center;justify-content:space-between;font-size:12px;">
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($attachment->file_path) }}" target="_blank" style="color:var(--brand);text-decoration:none;">
                                <i class="fa-solid fa-image" style="font-size:11px;"></i> {{ $attachment->file_name ?? 'Lampiran' }}
                            </a>
                            <button type="button" wire:click="deleteAttachment('{{ $attachment->id }}')" style="color:var(--danger-text);background:none;border:none;cursor:pointer;font-size:11px;">Hapus</button>
                        </div>
                    @empty
                        <p style="font-size:12px;color:var(--text-muted);margin:0;">Belum ada lampiran.</p>
                    @endforelse
                </div>
                <input type="file" wire:model="newAttachment" accept="image/*" class="form-input" style="font-size:12px;">
                @error('newAttachment') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                @if ($newAttachment)
                    <button type="button" wire:click="uploadAttachment" class="btn-ghost" style="margin-top:6px;font-size:12px;padding:4px 10px;">Unggah</button>
                @endif
            </div>

            <div style="display:flex;justify-content:flex-end;">
                <button type="button" wire:click="deleteTask('{{ $task->id }}')" wire:confirm="Yakin hapus task ini?" style="color:var(--danger-text);background:none;border:none;cursor:pointer;font-size:12px;font-weight:600;">
                    <i class="fa-solid fa-trash" style="font-size:11px;"></i> Hapus Task
                </button>
            </div>
        </div>
    </div>
@endif

<!-- MODAL: KELOLA MEMBER -->
@if ($showMemberModal)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:50;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click="$set('showMemberModal', false)">
        <div style="background:var(--surface);border-radius:14px;padding:20px;max-width:440px;width:100%;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Kelola Member</h3>

            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
                @foreach ($this->members as $member)
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;">
                        <span>{{ $member->user?->name }} <span class="badge badge-cancelled">{{ ucfirst($member->role) }}</span></span>
                        @if ($member->role !== 'owner')
                            <div style="display:flex;gap:8px;">
                                @if ($member->role === 'member')
                                    <button type="button" wire:click="promoteToAssigner('{{ $member->id }}')" style="color:var(--brand);background:none;border:none;cursor:pointer;font-size:11px;">Jadikan Assigner</button>
                                @endif
                                <button type="button" wire:click="removeMember('{{ $member->id }}')" wire:confirm="Keluarkan member ini?" style="color:var(--danger-text);background:none;border:none;cursor:pointer;font-size:11px;">Keluarkan</button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div style="display:flex;gap:8px;">
                <select class="form-input" wire:model="newMemberUserId">
                    <option value="">Pilih karyawan...</option>
                    @foreach ($this->companyUsers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="addMember" class="btn-primary" style="white-space:nowrap;">Tambah</button>
            </div>
            @error('newMemberUserId') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror

            <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                <button type="button" wire:click="$set('showMemberModal', false)" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif
</div>
