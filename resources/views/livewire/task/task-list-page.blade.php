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
        <button type="button" wire:click="selectScope('saya')" class="doc-type-btn {{ ! $activeProjectId && $scope === 'saya' ? 'is-active' : '' }}">
            <i class="fa-solid fa-user"></i> <span>Tugas Saya</span>
        </button>
        <button type="button" wire:click="selectScope('dibuat')" class="doc-type-btn {{ ! $activeProjectId && $scope === 'dibuat' ? 'is-active' : '' }}">
            <i class="fa-solid fa-pen"></i> <span>Saya Buat</span>
        </button>
        @if ($this->hasSubordinates)
            <button type="button" wire:click="selectScope('tim')" class="doc-type-btn {{ ! $activeProjectId && $scope === 'tim' ? 'is-active' : '' }}">
                <i class="fa-solid fa-users"></i> <span>Tim Saya</span>
            </button>
        @endif
        <div style="font-size:10.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;padding:14px 10px 4px;">Riwayat</div>

        <button type="button" wire:click="selectRiwayatStatus('complete')" class="doc-type-btn {{ ! $activeProjectId && $scope === 'riwayat' && $riwayatStatus === 'complete' ? 'is-active' : '' }}">
            <i class="fa-solid fa-circle-check"></i> <span>Selesai</span>
        </button>
        <button type="button" wire:click="selectRiwayatStatus('rejected')" class="doc-type-btn {{ ! $activeProjectId && $scope === 'riwayat' && $riwayatStatus === 'rejected' ? 'is-active' : '' }}">
            <i class="fa-solid fa-circle-xmark"></i> <span>Ditolak</span>
        </button>

        <div style="font-size:10.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;padding:14px 10px 4px;">Project</div>

        @foreach ($this->myProjects as $project)
            <button type="button" wire:click="selectProject('{{ $project->id }}')" class="doc-type-btn {{ $activeProjectId === $project->id ? 'is-active' : '' }}">
                <span style="width:8px;height:8px;border-radius:50%;background:{{ $project->color }};flex-shrink:0;"></span>
                <span>{{ $project->name }}</span>
            </button>
        @endforeach

        <button type="button" wire:click="openCreateProjectModal" class="doc-type-btn" style="opacity:0.8;">
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
                @elseif ($scope === 'riwayat')
                    Riwayat &middot; {{ $riwayatStatus === 'rejected' ? 'Ditolak' : 'Selesai' }}
                @else
                    {{ ['saya' => 'Tugas Saya', 'dibuat' => 'Saya Buat', 'tim' => 'Tim Saya'][$scope] ?? 'Tugas' }}
                @endif
            </h3>
            <div style="display:flex;gap:10px;align-items:center;">
                @if ($this->activeProject)
                    <button type="button" wire:click="openMembersModal" class="btn-ghost btn-sm"><i class="fa-solid fa-users"></i> Anggota ({{ $this->projectMembers->count() }})</button>
                @endif
                <div style="display:flex;gap:6px;">
                    <button type="button" wire:click="$set('view', 'board')" class="btn-sm {{ $view === 'board' ? 'btn-primary' : 'btn-ghost' }}"><i class="fa-solid fa-table-columns"></i> Board</button>
                    <button type="button" wire:click="$set('view', 'list')" class="btn-sm {{ $view === 'list' ? 'btn-secondary' : 'btn-ghost' }}"><i class="fa-solid fa-list"></i> List</button>
                </div>
                <button type="button" wire:click="openCreateTask" class="btn-primary btn-sm"><i class="fa-solid fa-plus" style="font-size:12px;"></i> Tugas Baru</button>
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
                                    <div x-data="{ showComments: false }" style="background:var(--surface-alt);border:1px solid var(--border);border-radius:10px;padding:10px 12px;">
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
                                            <select wire:change="moveTaskStatus('{{ $task->id }}', $event.target.value)" class="form-input" style="font-size:11px;padding:4px 6px;height:auto;margin-top:8px;" @disabled($task->status === \App\Enum\Task\TaskStatus::COMPLETE->value)>
                                                @foreach (\App\Enum\Task\TaskStatus::BOARD_ORDER as $moveTo)
                                                    @continue($moveTo === \App\Enum\Task\TaskStatus::COMPLETE && $task->reviews_count)
                                                    <option value="{{ $moveTo->value }}" @selected($moveTo->value === $task->status)>{{ $moveTo === \App\Enum\Task\TaskStatus::REVIEW ? 'Kirim untuk Tinjau' : $moveTo->getLabel() }}</option>
                                                @endforeach
                                            </select>
                                        @endif

                                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;padding-top:8px;border-top:1px solid var(--border-soft);">
                                            <button type="button" wire:click="openTaskDetail('{{ $task->id }}')" class="btn-ghost" style="padding:4px 8px;font-size:11px;"><i class="fa-solid fa-circle-info"></i> Detail</button>
                                            <button type="button" wire:click="openAttachments('{{ $task->id }}')" class="btn-ghost" style="padding:4px 8px;font-size:11px;"><i class="fa-solid fa-paperclip"></i> Lampiran ({{ $task->attachments_count }})</button>
                                            <button type="button" @click="showComments = !showComments" class="btn-ghost" style="padding:4px 8px;font-size:11px;"><i class="fa-solid fa-comment"></i> Komentar ({{ $task->comments->count() }})</button>
                                        </div>

                                        <div x-show="showComments" x-cloak style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border-soft);">
                                            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px;max-height:150px;overflow-y:auto;">
                                                @forelse ($task->comments as $comment)
                                                    <div style="font-size:11.5px;">
                                                        <span style="font-weight:700;">{{ $comment->user?->name }}</span>
                                                        <span style="color:var(--text-muted);"> &middot; {{ $comment->created_at->diffForHumans() }}</span>
                                                        <p style="margin:2px 0 0;">{{ $comment->comment }}</p>
                                                    </div>
                                                @empty
                                                    <p style="font-size:11.5px;color:var(--text-muted);margin:0;">Belum ada komentar.</p>
                                                @endforelse
                                            </div>
                                            <div style="display:flex;gap:6px;">
                                                <input type="text" class="form-input" style="font-size:11.5px;padding:6px 8px;" wire:model="newComment" wire:keydown.enter.prevent="addComment('{{ $task->id }}')" placeholder="Tulis komentar...">
                                                <button type="button" wire:click="addComment('{{ $task->id }}')" class="btn-ghost" style="padding:4px 10px;font-size:11.5px;">Kirim</button>
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
                    <label class="form-label">Reviewer (opsional)</label>
                    <div wire:ignore>
                        <select data-tom-select data-placeholder="Pilih reviewer..." multiple wire:model="reviewerIds">
                            @foreach ($this->reviewerOptions as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kalau diisi, tugas baru Selesai setelah SEMUA reviewer yang dipilih menyetujui.</p>
                </div>

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

            @if ($task->assigned_to === auth()->id() && $task->rejected_reason)
                {{-- Catatan penolakan — cuma kelihatan buat pelaksana, bukan komentar publik. --}}
                <div style="background:var(--danger-soft-bg);border:1px solid var(--danger-soft-border);border-radius:12px;padding:12px 14px;margin-bottom:14px;">
                    <p style="font-size:11.5px;font-weight:700;color:var(--danger-soft-text);margin:0 0 4px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Tinjauan Ditolak
                        @if ($task->rejectionNoteBy) oleh {{ $task->rejectionNoteBy->name }} @endif
                    </p>
                    <p style="font-size:12.5px;color:var(--danger-soft-text);margin:0;">{{ $task->rejected_reason }}</p>
                </div>
            @endif

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

            @if ($task->status === TaskStatus::REVIEW->value || $task->reviews->isNotEmpty())
                @php
                    $myReviewRow = $this->myReviewRow;
                    $canApprove = $myReviewRow && $myReviewRow->status === 'pending';
                    $canActOnReview = $task->status === TaskStatus::REVIEW->value
                        && ($canApprove || (! $myReviewRow && $task->created_by === auth()->id()));
                @endphp
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                    <h4 style="font-size:13px;font-weight:700;margin:0 0 8px;">Review</h4>

                    @foreach ($task->reviews as $reviewRow)
                        <div style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px dashed var(--border-soft);">
                            <div style="display:flex;align-items:center;gap:8px;font-size:12.5px;margin-bottom:4px;">
                                <span style="flex:1;font-weight:600;">{{ $reviewRow->reviewer?->name }}</span>
                                @if ($reviewRow->status === 'approved')
                                    <span class="badge badge-approved">Disetujui</span>
                                @elseif ($reviewRow->status === 'rejected')
                                    <span class="badge badge-rejected">Ditolak</span>
                                @else
                                    <span class="badge badge-pending">Menunggu</span>
                                @endif
                            </div>
                            @if ($reviewRow->status === 'approved')
                                <div style="color:#f59e0b;margin-bottom:2px;">
                                    @for ($i = 1; $i <= 5; $i++)<i class="fa-{{ $i <= $reviewRow->rating ? 'solid' : 'regular' }} fa-star"></i>@endfor
                                </div>
                                @if ($reviewRow->comment)<p style="font-size:12.5px;margin:0;">{{ $reviewRow->comment }}</p>@endif
                            @elseif ($reviewRow->status === 'rejected' && $reviewRow->rejected_note)
                                <p style="font-size:12.5px;color:var(--danger-text);margin:0;">{{ $reviewRow->rejected_note }}</p>
                            @endif
                        </div>
                    @endforeach

                    @if ($canActOnReview)
                        @if (! $showRejectForm)
                            @if ($canApprove)
                                <div style="display:flex;gap:4px;margin-bottom:8px;">
                                    @foreach (range(1, 5) as $star)
                                        <label style="cursor:pointer;">
                                            <input type="radio" wire:model.live="reviewRating" value="{{ $star }}" style="display:none;">
                                            <i class="fa-{{ $reviewRating >= $star ? 'solid' : 'regular' }} fa-star" style="color:#f59e0b;"></i>
                                        </label>
                                    @endforeach
                                </div>
                                <textarea class="form-input" rows="2" wire:model="reviewComment" placeholder="Komentar (opsional)"></textarea>
                                @error('reviewRating') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                            @endif
                            <div style="display:flex;gap:8px;margin-top:8px;">
                                @if ($canApprove)
                                    <button type="button" wire:click="submitReview('{{ $task->id }}')" class="btn-primary btn-sm">Setujui</button>
                                @endif
                                <button type="button" wire:click="openRejectForm" class="btn-ghost btn-sm" style="color:var(--danger-text);">Tolak</button>
                            </div>
                        @else
                            <p style="font-size:12px;color:var(--text-muted);margin:0 0 6px;">Catatan ini cuma kelihatan buat pelaksana ({{ $task->assignedTo?->name }}), tugas dikembalikan ke "Sedang Dalam Proses".</p>
                            <textarea class="form-input" rows="3" wire:model="rejectNote" placeholder="Kenapa ditolak? (wajib diisi)"></textarea>
                            @error('rejectNote') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                            <div style="display:flex;gap:8px;margin-top:8px;">
                                <button type="button" wire:click="submitReject('{{ $task->id }}')" class="btn-primary btn-sm" style="background:var(--danger-text);">Kirim Penolakan</button>
                                <button type="button" wire:click="cancelRejectForm" class="btn-ghost btn-sm">Batal</button>
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif

@if ($this->attachmentsTask)
    @php $attachTask = $this->attachmentsTask; @endphp
    <div class="modal-overlay" wire:click="closeAttachments">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">Lampiran — {{ $attachTask->code }}</h3>
                <button type="button" wire:click="closeAttachments" class="btn-ghost" style="padding:4px 8px;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;">
                @forelse ($attachTask->attachments as $attachment)
                    @php $isImage = ! $attachment->isLink() && preg_match('/\.(jpe?g|png|gif|webp)$/i', $attachment->file_path ?? ''); @endphp
                    <div style="display:flex;align-items:center;gap:8px;font-size:12.5px;">
                        @if ($isImage)
                            <div x-data="{ open: false }" style="display:contents;">
                                <img src="{{ $attachment->file_url }}" @click="open = true" style="width:44px;height:44px;object-fit:cover;border-radius:6px;cursor:pointer;flex-shrink:0;" alt="{{ $attachment->file_name }}">
                                <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $attachment->file_name ?? 'Gambar' }}</span>
                                <div x-show="open" x-cloak @click="open = false" style="position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:10000;display:flex;align-items:center;justify-content:center;padding:24px;">
                                    <img src="{{ $attachment->file_url }}" style="max-width:90vw;max-height:90vh;border-radius:8px;object-fit:contain;" @click.stop alt="{{ $attachment->file_name }}">
                                </div>
                            </div>
                        @elseif ($attachment->isLink())
                            <i class="fa-solid fa-link" style="color:var(--text-faint);width:14px;flex-shrink:0;"></i>
                            <a href="{{ $attachment->url }}" target="_blank" rel="noopener" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#1a56c4;">{{ $attachment->file_name ?? $attachment->url }}</a>
                        @else
                            <i class="fa-solid fa-paperclip" style="color:var(--text-faint);width:14px;flex-shrink:0;"></i>
                            <a href="{{ $attachment->file_url }}" target="_blank" rel="noopener" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#1a56c4;">{{ $attachment->file_name ?? 'File' }}</a>
                        @endif
                        <button type="button" wire:click="removeAttachment('{{ $attachment->id }}')" wire:confirm="Hapus lampiran ini?" style="background:none;border:none;color:var(--danger-text);cursor:pointer;flex-shrink:0;"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                @empty
                    <p style="font-size:12px;color:var(--text-muted);margin:0;">Belum ada lampiran.</p>
                @endforelse
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                <input type="file" wire:model="newAttachmentFile" style="font-size:11.5px;max-width:180px;">
                <button type="button" wire:click="uploadFileAttachment" wire:loading.attr="disabled" wire:target="newAttachmentFile,uploadFileAttachment" class="btn-ghost" style="padding:5px 10px;font-size:11.5px;">Unggah</button>
                <input type="url" class="form-input" style="max-width:180px;font-size:11.5px;padding:6px 8px;" wire:model="newAttachmentLink" placeholder="https://...">
                <input type="text" class="form-input" style="max-width:140px;font-size:11.5px;padding:6px 8px;" wire:model="newAttachmentLinkLabel" placeholder="Judul link (opsional)">
                <button type="button" wire:click="addLinkAttachment" class="btn-ghost" style="padding:5px 10px;font-size:11.5px;">Tambah Link</button>
            </div>
            @error('newAttachmentFile') <p style="font-size:11px;color:var(--danger-text);margin-top:4px;">{{ $message }}</p> @enderror
            @error('newAttachmentLink') <p style="font-size:11px;color:var(--danger-text);margin-top:4px;">{{ $message }}</p> @enderror
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
                            @elseif ($member->is_assigner)
                                <span class="badge">Assigner</span>
                            @endif
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if ($this->canManageProjectMembers && $member->role !== 'owner')
                                <label class="custom-checkbox" style="--accent:#0f766e;" title="Boleh assign/bikin task untuk anggota lain">
                                    <input type="checkbox" @checked($member->is_assigner) wire:click="toggleAssigner('{{ $member->user_id }}', {{ $member->is_assigner ? 'false' : 'true' }})">
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </label>
                                <button type="button" wire:click="removeProjectMember('{{ $member->user_id }}')" wire:confirm="Keluarkan anggota ini?" style="color:var(--danger-text);background:none;border:none;cursor:pointer;">
                                    <i class="fa-solid fa-user-minus"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->canManageProjectMembers)
                <div style="display:flex;gap:6px;align-items:flex-start;">
                    <div wire:ignore style="flex:1;">
                        <select data-tom-select data-placeholder="-- Pilih orang --" wire:model="newProjectMemberId">
                            <option value=""></option>
                            @foreach ($this->companyUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" wire:click="addProjectMember" class="btn-primary btn-sm">Tambah</button>
                </div>
            @endif
        </div>
    </div>
@endif
</div>
