<div>
@php
    use App\Enum\Task\TaskStatus;
    use App\Enum\Task\TaskPriority;
    $boardStatuses = [TaskStatus::PENDING, ...TaskStatus::BOARD_ORDER];
    // Task::due_date sengaja tidak di-cast ke date di model (lihat komentar di Task.php) — parse
    // manual di sini, bukan panggil ->translatedFormat() langsung di atas string mentah.
    $fmtDate = fn ($d, $f = 'j M') => $d ? \Carbon\Carbon::parse($d)->translatedFormat($f) : null;
@endphp

<x-page-header title="Tugas" subtitle="Task harian tim & tugas formal yang butuh approval" />

<div class="doc-type-layout">
    {{-- Sidebar ala Lark: scope pribadi di atas, daftar project di bawah — cuma di halaman ini. --}}
    <div class="doc-type-nav">
        <button type="button" wire:click="selectScope('saya')" class="doc-type-btn doc-type-btn-sky {{ ! $activeProjectId && $scope === 'saya' ? 'is-active' : '' }}">
            <i class="fa-solid fa-user"></i> <span>Tugas Saya</span>
        </button>
        <button type="button" wire:click="selectScope('dibuat')" class="doc-type-btn doc-type-btn-sky {{ ! $activeProjectId && $scope === 'dibuat' ? 'is-active' : '' }}">
            <i class="fa-solid fa-pen"></i> <span>Saya Buat</span>
        </button>
        @if ($this->hasSubordinates)
            <button type="button" wire:click="selectScope('tim')" class="doc-type-btn doc-type-btn-sky {{ ! $activeProjectId && $scope === 'tim' ? 'is-active' : '' }}">
                <i class="fa-solid fa-users"></i> <span>Tim Saya</span>
            </button>
        @endif

        <div style="font-size:10.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;padding:14px 10px 4px;">Project</div>

        @foreach ($this->myProjects as $project)
            <button type="button" wire:click="selectProject('{{ $project->id }}')" class="doc-type-btn doc-type-btn-sky {{ $activeProjectId === $project->id ? 'is-active' : '' }}">
                <span style="width:8px;height:8px;border-radius:50%;background:{{ $project->color }};flex-shrink:0;"></span>
                <span>{{ $project->name }}</span>
            </button>
        @endforeach

        <button type="button" wire:click="openCreateProjectModal" class="doc-type-btn doc-type-btn-sky" style="opacity:0.8;">
            <i class="fa-solid fa-plus"></i> <span>Project Baru</span>
        </button>
    </div>

    {{-- Konten kanan --}}
    <div style="flex:1;min-width:0;">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;display:flex;align-items:center;gap:8px;">
                @if ($this->activeProject)
                    <span style="width:10px;height:10px;border-radius:50%;background:{{ $this->activeProject->color }};"></span>
                    {{ $this->activeProject->name }}
                @else
                    {{ ['saya' => 'Tugas Saya', 'dibuat' => 'Saya Buat', 'tim' => 'Tim Saya'][$scope] ?? 'Tugas' }}
                @endif
            </h3>
            <div style="display:flex;gap:10px;align-items:center;">
                @if ($this->activeProject)
                    <button type="button" wire:click="openMembersModal" class="btn-ghost"><i class="fa-solid fa-users"></i> Anggota ({{ $this->projectMembers->count() }})</button>
                @endif
                <div style="display:flex;gap:6px;">
                    <button type="button" wire:click="$set('view', 'board')" class="{{ $view === 'board' ? 'btn-primary' : 'btn-ghost' }}"><i class="fa-solid fa-table-columns"></i> Board</button>
                    <button type="button" wire:click="$set('view', 'list')" class="{{ $view === 'list' ? 'btn-primary' : 'btn-ghost' }}"><i class="fa-solid fa-list"></i> List</button>
                </div>
                <button type="button" wire:click="openCreateTask" class="btn-primary"><i class="fa-solid fa-plus" style="font-size:12px;"></i> Tugas Baru</button>
            </div>
        </div>

        @if ($view === 'board')
            <div x-data="{ zoom: 1 }" style="position:relative;">
                <div style="background:var(--surface-page);border-radius:20px;padding:16px;overflow:auto;min-height:70vh;border:1px solid var(--border-soft);" :style="{ zoom: zoom }">
                    <div style="display:flex;gap:14px;min-width:min-content;align-items:flex-start;">
                    @foreach ($boardStatuses as $status)
                        <div style="flex:0 0 280px;min-width:280px;background:var(--surface);border-radius:12px;overflow:hidden;">
                            <div style="background:{{ $status->getColor() === 'gray' ? '#64748b' : ($status->getColor() === 'warning' ? '#d97706' : ($status->getColor() === 'info' ? '#1a56c4' : ($status->getColor() === 'success' ? '#047C54' : '#64748b'))) }};padding:10px 12px;display:flex;align-items:center;justify-content:space-between;">
                                <span style="color:#fff;font-weight:700;font-size:13px;">{{ $status->getLabel() }}</span>
                                <span style="background:rgba(255,255,255,0.25);color:#fff;font-size:11px;font-weight:700;padding:2px 9px;border-radius:99px;">{{ count($this->tasksByStatus[$status->value] ?? []) }}</span>
                            </div>
                            <div style="min-height:80px;padding:8px;display:flex;flex-direction:column;gap:8px;">
                                @forelse (($this->tasksByStatus[$status->value] ?? []) as $task)
                                    <div wire:click="openTaskDetail('{{ $task->id }}')" style="cursor:pointer;background:var(--surface-alt);border:1px solid var(--border);border-radius:10px;padding:10px 12px;">
                                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0 0 6px;">{{ \Illuminate\Support\Str::limit($task->requirement, 80) }}</p>
                                        <div style="display:flex;align-items:center;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:6px;">
                                            <span class="badge badge-cancelled">{{ $task->priority?->getLabel() ?? $task->priority }}</span>
                                            @if ($task->due_date)
                                                <span><i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $fmtDate($task->due_date) }}</span>
                                            @endif
                                        </div>
                                        @if ($task->assignedTo)
                                            <div style="display:flex;align-items:center;gap:6px;">
                                                <x-avatar :user="$task->assignedTo" :size="18" />
                                                <span style="font-size:11px;color:var(--text-muted);">{{ $task->assignedTo->name }}</span>
                                            </div>
                                        @endif
                                        @if ($status !== TaskStatus::PENDING)
                                            <select wire:click.stop wire:change="moveTaskStatus('{{ $task->id }}', $event.target.value)" class="form-input" style="font-size:11px;padding:4px 6px;height:auto;margin-top:8px;" @disabled($task->status === \App\Enum\Task\TaskStatus::COMPLETE->value)>
                                                @foreach (\App\Enum\Task\TaskStatus::BOARD_ORDER as $moveTo)
                                                    <option value="{{ $moveTo->value }}" @selected($moveTo->value === $task->status)>{{ $moveTo->getLabel() }}</option>
                                                @endforeach
                                            </select>
                                        @endif
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
                    <button type="button" @click="zoom = Math.max(0.5, +(zoom - 0.1).toFixed(2))" style="width:26px;height:26px;border-radius:50%;border:none;background:rgba(255,255,255,0.15);color:#fff;cursor:pointer;font-size:12px;"><i class="fa-solid fa-minus"></i></button>
                    <span x-text="Math.round(zoom * 100) + '%'" style="color:#fff;font-size:11px;font-weight:700;width:38px;text-align:center;"></span>
                    <button type="button" @click="zoom = Math.min(1.5, +(zoom + 0.1).toFixed(2))" style="width:26px;height:26px;border-radius:50%;border:none;background:rgba(255,255,255,0.15);color:#fff;cursor:pointer;font-size:12px;"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>
        @else
            <div style="overflow-x:auto;background:var(--surface);border-radius:12px;border:1px solid var(--border);">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left;font-size:11px;color:var(--text-muted);border-bottom:1px solid var(--border);">
                            <th style="padding:10px 14px;">Kode</th>
                            <th style="padding:10px 14px;">Kebutuhan</th>
                            <th style="padding:10px 14px;">Status</th>
                            <th style="padding:10px 14px;">Prioritas</th>
                            <th style="padding:10px 14px;">Ditugaskan</th>
                            <th style="padding:10px 14px;">Tenggat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->tasks as $task)
                            <tr wire:click="openTaskDetail('{{ $task->id }}')" style="cursor:pointer;border-bottom:1px solid var(--border);font-size:13px;">
                                <td style="padding:10px 14px;font-weight:600;">{{ $task->code }}</td>
                                <td style="padding:10px 14px;">{{ \Illuminate\Support\Str::limit($task->requirement, 60) }}</td>
                                <td style="padding:10px 14px;">
                                    <span class="badge" style="background:transparent;color:var(--text-muted);">{{ TaskStatus::tryFrom($task->status)?->getLabel() ?? $task->status }}</span>
                                </td>
                                <td style="padding:10px 14px;">{{ $task->priority?->getLabel() ?? $task->priority }}</td>
                                <td style="padding:10px 14px;">{{ $task->assignedTo?->name ?? '-' }}</td>
                                <td style="padding:10px 14px;">{{ $fmtDate($task->due_date, 'j M Y') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="padding:20px;text-align:center;color:var(--text-muted);">Belum ada tugas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@if ($showTaskModal)
    <div class="modal-overlay" wire:click="closeTaskModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">
                Tugas Baru @if ($this->activeProject) <span style="font-weight:400;color:var(--text-muted);font-size:13px;">di {{ $this->activeProject->name }}</span> @endif
            </h3>
            <form wire:submit="createTask" style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label class="form-label">Kebutuhan / Deskripsi *</label>
                    <textarea class="form-input" rows="3" wire:model="requirement"></textarea>
                    @error('requirement') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div class="date-grid">
                    <div>
                        <label class="form-label">Tenggat</label>
                        <input type="date" class="form-input" wire:model="due_date">
                    </div>
                    <div>
                        <label class="form-label">Prioritas</label>
                        <select class="form-input" wire:model="priority">
                            @foreach (TaskPriority::cases() as $p)
                                <option value="{{ $p->value }}">{{ $p->getLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @unless ($this->activeProject)
                    <div style="display:flex;align-items:center;gap:10px;">
                        <label class="custom-checkbox" style="--accent:#042c6c;">
                            <input type="checkbox" wire:model.live="requiresApproval">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <span style="font-size:13px;">Butuh Approval</span>
                    </div>
                @endunless

                @if ($requiresApproval && ! $this->activeProject)
                    <div>
                        <label class="form-label">Jenis Layanan *</label>
                        <select class="form-input" wire:model="service_id">
                            <option value="">-- Pilih --</option>
                            @foreach ($this->taskServices as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </select>
                        @error('service_id') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                @elseif ($this->activeProject)
                    <div>
                        <label class="form-label">Tugaskan ke</label>
                        <div wire:ignore>
                            <select data-tom-select data-placeholder="Saya sendiri" wire:model="assigned_to">
                                <option value="">Saya sendiri</option>
                                @foreach ($this->projectMembers as $member)
                                    @continue($member->user_id === auth()->id())
                                    <option value="{{ $member->user_id }}">{{ $member->user?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @elseif ($this->hasSubordinates)
                    <div>
                        <label class="form-label">Tugaskan ke</label>
                        <div wire:ignore>
                            <select data-tom-select data-placeholder="Saya sendiri" wire:model="assigned_to">
                                <option value="">Saya sendiri</option>
                                @foreach ($this->subordinates as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endif

                <div>
                    <label class="form-label">Checklist</label>
                    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:6px;">
                        @foreach ($checklist as $i => $item)
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span style="flex:1;font-size:12px;">{{ $item }}</span>
                                <button type="button" wire:click="removeChecklistDraft({{ $i }})" style="color:var(--danger-text);background:none;border:none;cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        @endforeach
                    </div>
                    <div style="display:flex;gap:6px;">
                        <input type="text" class="form-input" wire:model="newChecklistTitle" wire:keydown.enter.prevent="addChecklistDraft" placeholder="Tambah item...">
                        <button type="button" wire:click="addChecklistDraft" class="btn-ghost">Tambah</button>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" wire:click="closeTaskModal" class="btn-ghost">Batal</button>
                    <button type="submit" class="btn-primary">Buat Tugas</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($this->selectedTask)
    @php $task = $this->selectedTask; @endphp
    <div class="modal-overlay" wire:click="closeTaskDetail">
        <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">{{ $task->code }}</h3>
                <button type="button" wire:click="closeTaskDetail" class="btn-ghost" style="padding:4px 8px;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <p style="font-size:13px;color:var(--text-heading);margin:0 0 10px;">{{ $task->requirement }}</p>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
                <span class="badge">{{ TaskStatus::tryFrom($task->status)?->getLabel() ?? $task->status }}</span>
                <span class="badge badge-cancelled">{{ $task->priority?->getLabel() ?? $task->priority }}</span>
                @if ($task->due_date)<span class="badge">Tenggat: {{ $fmtDate($task->due_date, 'j M Y') }}</span>@endif
                @if ($task->project)<span class="badge"><i class="fa-solid fa-diagram-project"></i> {{ $task->project->name }}</span>@endif
            </div>

            <div style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">
                Dibuat oleh {{ $task->createdBy?->name ?? '-' }}
                @if ($task->assignedTo) &middot; ditugaskan ke {{ $task->assignedTo->name }} @endif
            </div>

            <div style="margin-bottom:16px;">
                <h4 style="font-size:13px;font-weight:700;margin:0 0 8px;">Checklist</h4>
                @forelse ($task->checklistItems as $item)
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:6px;">
                        <input type="checkbox" wire:click="toggleChecklistItem('{{ $item->id }}')" @checked($item->is_done)>
                        <span style="{{ $item->is_done ? 'text-decoration:line-through;color:var(--text-muted);' : '' }}">{{ $item->title }}</span>
                    </label>
                @empty
                    <p style="font-size:12px;color:var(--text-muted);">Tidak ada checklist.</p>
                @endforelse
            </div>

            <div>
                <h4 style="font-size:13px;font-weight:700;margin:0 0 8px;">Komentar</h4>
                <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:10px;max-height:180px;overflow-y:auto;">
                    @forelse ($task->comments as $comment)
                        <div style="font-size:12px;">
                            <span style="font-weight:700;">{{ $comment->user?->name }}</span>
                            <span style="color:var(--text-muted);"> &middot; {{ $comment->created_at->diffForHumans() }}</span>
                            <p style="margin:2px 0 0;">{{ $comment->comment }}</p>
                        </div>
                    @empty
                        <p style="font-size:12px;color:var(--text-muted);">Belum ada komentar.</p>
                    @endforelse
                </div>
                <div style="display:flex;gap:6px;">
                    <input type="text" class="form-input" wire:model="newComment" wire:keydown.enter.prevent="addComment" placeholder="Tulis komentar...">
                    <button type="button" wire:click="addComment" class="btn-ghost">Kirim</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if ($showProjectModal)
    <div class="modal-overlay" wire:click="closeProjectModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Project Baru</h3>
            <form wire:submit="createProject" style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label class="form-label">Nama Project *</label>
                    <input type="text" class="form-input" wire:model="projectName">
                    @error('projectName') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-input" rows="2" wire:model="projectDescription"></textarea>
                </div>
                <div>
                    <label class="form-label">Warna</label>
                    <input type="color" wire:model="projectColor" style="width:60px;height:36px;border:1px solid var(--border);border-radius:8px;">
                </div>
                <div>
                    <label class="form-label">Anggota Awal</label>
                    <div wire:ignore>
                        <select data-tom-select data-placeholder="Pilih anggota..." multiple wire:model="projectMemberIds">
                            @foreach ($this->companyUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" wire:click="closeProjectModal" class="btn-ghost">Batal</button>
                    <button type="submit" class="btn-primary">Buat Project</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($showMembersModal && $this->activeProject)
    <div class="modal-overlay" wire:click="closeMembersModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">Anggota {{ $this->activeProject->name }}</h3>
                <button type="button" wire:click="closeMembersModal" class="btn-ghost" style="padding:4px 8px;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">
                @foreach ($this->projectMembers as $member)
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <x-avatar :user="$member->user" :size="20" />
                            <span style="font-size:13px;">{{ $member->user?->name }}</span>
                            @if ($member->role === 'owner')
                                <span class="badge">Owner</span>
                            @endif
                        </div>
                        @if ($this->canManageProjectMembers && $member->role !== 'owner')
                            <button type="button" wire:click="removeProjectMember('{{ $member->user_id }}')" wire:confirm="Keluarkan anggota ini?" style="color:var(--danger-text);background:none;border:none;cursor:pointer;">
                                <i class="fa-solid fa-user-minus"></i>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($this->canManageProjectMembers)
                <div style="display:flex;gap:6px;">
                    <select class="form-input" wire:model="newProjectMemberId">
                        <option value="">-- Pilih orang --</option>
                        @foreach ($this->companyUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="addProjectMember" class="btn-primary">Tambah</button>
                </div>
            @endif
        </div>
    </div>
@endif
</div>
