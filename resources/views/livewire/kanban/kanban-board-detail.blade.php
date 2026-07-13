<div>
@php
    $statusLabel = fn (string $s) => ['todo' => 'Belum Dikerjakan', 'in_progress' => 'Dikerjakan', 'review' => 'Review', 'done' => 'Selesai'][$s] ?? $s;
    $statusBadge = fn (string $s) => ['todo' => 'badge-cancelled', 'in_progress' => 'badge-pending', 'review' => 'badge-pending', 'done' => 'badge-approved'][$s] ?? 'badge-cancelled';
    $statusColor = fn (string $s) => ['todo' => '#64748b', 'in_progress' => '#1a56c4', 'review' => '#d97706', 'done' => '#047C54'][$s] ?? '#64748b';
    $priorityLabel = fn (string $p) => ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'urgent' => 'Mendesak'][$p] ?? $p;
@endphp

@php
    $initials = fn (?string $name) => collect(explode(' ', $name ?? '?'))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

<x-page-header :title="$board->name" :subtitle="$board->description" :back="route('kanban')" />

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
        @foreach ($this->members as $member)
            <div title="{{ $member->user?->name }} · {{ ucfirst($member->role) }}" style="width:32px;height:32px;border-radius:50%;background:#042c6c;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;">
                {{ $initials($member->user?->name) }}
            </div>
        @endforeach
        @if ($this->canAddMember)
            <button type="button" wire:click="openMemberModal" class="btn-ghost" style="padding:6px 12px;font-size:12px;">
                <i class="fa-solid fa-user-plus" style="font-size:11px;"></i> Kelola
            </button>
        @endif
    </div>
    <button type="button" wire:click="openCreateTask" class="btn-primary">
        <i class="fa-solid fa-plus" style="font-size:12px;"></i> Task Baru
    </button>
</div>

<!-- KANBAN WORKSPACE — background gradasi navy (identik warna navigasi desktop), setinggi minimal
     1 layar penuh, 1 container overflow-y:auto + overflow-x:auto supaya kolomnya bisa di-zoom
     tanpa layout pecah. Header tiap kolom masuk ke dalam box putihnya sendiri, warna beda per status.
     Zoom pakai CSS `zoom` (bukan transform:scale) supaya scrollbar/overflow ikut menyesuaikan
     otomatis tanpa perhitungan ukuran manual. -->
<div x-data="{ zoom: 1 }" style="position:relative;">
    <div style="background:linear-gradient(130deg,#042c6c 0%,#B9308C 100%);border-radius:20px;padding:16px;overflow-y:auto;overflow-x:auto;min-height:100vh;max-height:100vh;" :style="{ zoom: zoom }">
        <div style="display:flex;gap:14px;min-width:min-content;align-items:flex-start;">
        @foreach (\App\Models\Kanban\KanbanTask::STATUSES as $status)
            <div style="flex:0 0 280px;min-width:280px;background:var(--surface);border-radius:12px;overflow:hidden;">
                <div style="background:{{ $statusColor($status) }};padding:10px 12px;display:flex;align-items:center;justify-content:space-between;">
                    <span style="color:#fff;font-weight:700;font-size:13px;">{{ $statusLabel($status) }}</span>
                    <span style="background:rgba(255,255,255,0.25);color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:99px;">{{ count($this->tasksByStatus[$status]) }}</span>
                </div>
                <div style="min-height:80px;padding:8px;display:flex;flex-direction:column;gap:8px;">
                    @forelse ($this->tasksByStatus[$status] as $task)
                        <div wire:click="openTask('{{ $task->id }}')" style="cursor:pointer;background:var(--surface-alt);border:1px solid var(--border);border-radius:10px;padding:10px 12px;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0 0 6px;">{{ $task->title }}</p>
                            <div style="display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--text-muted);">
                                @if ($task->assignedTo)
                                    <div title="{{ $task->assignedTo->name }}" style="width:22px;height:22px;border-radius:50%;background:#042c6c;display:flex;align-items:center;justify-content:center;color:#fff;font-size:9px;font-weight:700;flex-shrink:0;">
                                        {{ $initials($task->assignedTo->name) }}
                                    </div>
                                @else
                                    <span>-</span>
                                @endif
                                @if ($task->due_date)
                                    <span><i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $task->due_date->translatedFormat('j M') }}</span>
                                @endif
                            </div>
                            <div style="margin-top:6px;" onclick="event.stopPropagation()">
                                <select wire:change="moveTaskStatus('{{ $task->id }}', $event.target.value)" class="form-input" style="font-size:11px;padding:4px 6px;height:auto;">
                                    @foreach (\App\Models\Kanban\KanbanTask::STATUSES as $moveTo)
                                        <option value="{{ $moveTo }}" @selected($moveTo === $status)>{{ $statusLabel($moveTo) }}</option>
                                    @endforeach
                                </select>
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
                    <label class="form-label">Assign ke</label>
                    <select class="form-input" wire:model="assigned_to" @disabled(! $this->canAssign)>
                        <option value="">Diri Sendiri</option>
                        @if ($this->canAssign)
                            @foreach ($this->members as $member)
                                <option value="{{ $member->user_id }}">{{ $member->user?->name }}</option>
                            @endforeach
                        @endif
                    </select>
                    @unless ($this->canAssign)
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Cuma owner/assigner yang boleh assign ke orang lain.</p>
                    @endunless
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

            <!-- Komentar -->
            <div style="margin-bottom:14px;">
                <p class="form-label" style="margin-bottom:6px;">Komentar</p>
                <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:8px;max-height:180px;overflow-y:auto;">
                    @forelse ($task->comments as $comment)
                        <div style="font-size:12px;background:var(--surface-alt);border-radius:8px;padding:8px 10px;">
                            <p style="margin:0 0 2px;font-weight:600;color:var(--text-heading);">{{ $comment->user?->name }}</p>
                            <p style="margin:0;color:var(--text-body);">{{ $comment->comment }}</p>
                        </div>
                    @empty
                        <p style="font-size:12px;color:var(--text-muted);margin:0;">Belum ada komentar.</p>
                    @endforelse
                </div>
                <div style="display:flex;gap:8px;">
                    <input type="text" class="form-input" placeholder="Tulis komentar..." wire:model="newComment" wire:keydown.enter="addComment">
                    <button type="button" wire:click="addComment" class="btn-primary" style="padding:8px 14px;">Kirim</button>
                </div>
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
