<div>
@php
    use App\Enum\Task\TaskStatus;
    use App\Enum\Task\TaskPriority;
    // Todo Saya (self-assigned) lewatin REVIEW - langsung IN_PROGRESS -> COMPLETE.
    $isTodoHero = ! $activeProjectId && $scope === 'todo';
    $boardStatuses = $isTodoHero ? TaskStatus::BOARD_ORDER_SELF_ASSIGNED : TaskStatus::BOARD_ORDER;
    // Gaya "hero" (gradient + shape dekoratif punya sistem, lihat .page-hero/.a-deco-* di
    // app.css) dipakai di board pribadi (Todo Saya & Tugas Saya) - warna dari status-nya
    // sendiri (bukan index loop) biar Review konsisten dapat gold-dark di board manapun dia
    // muncul. Review = emas gelap ala aksen gold-shiny halaman profil, biar "butuh perhatian".
    $useBoardHero = $isTodoHero || (! $activeProjectId && $scope === 'saya');
    // Individu/Tim potensial banyak baris (banyak bawahan x banyak tugas) - tabel lebih gampang
    // di-scan/sortir daripada card yang makan tempat vertikal, beda kebutuhan sama Todo/Tugas Saya.
    $isTableScope = ! $activeProjectId && in_array($scope, ['individu', 'tim'], true);
    $heroClassFor = fn (TaskStatus $status) => match ($status) {
        TaskStatus::REVIEW => 'gold-dark',
        TaskStatus::IN_PROGRESS => 'gold',
        TaskStatus::COMPLETE => 'green',
        default => 'navy',
    };
    // Task::due_date sengaja tidak di-cast ke date di model (lihat komentar di Task.php) — parse
    // manual di sini, bukan panggil ->translatedFormat() langsung di atas string mentah.
    $fmtDate = fn ($d, $f = 'j M') => $d ? \Carbon\Carbon::parse($d)->translatedFormat($f) : null;
    // Map getColor() (gray/warning/info/success/danger) ke class .badge-* yang ada di app.css.
    $badgeClass = fn (string $color) => match ($color) {
        'warning' => 'badge-pending',
        'info' => 'badge-info',
        'success' => 'badge-approved',
        'danger' => 'badge-rejected',
        default => 'badge-cancelled',
    };
    // Aksen warna kartu tugas (border kiri + dot prioritas) — dari getColor() yang sama, bukan
    // pill baru. Hex literal di sini karena dipakai lewat inline CSS var, bukan class .badge-*.
    $priorityAccent = fn (string $color) => match ($color) {
        'warning' => '#a16207',
        'info' => '#1a56c4',
        'success' => '#15803d',
        'danger' => '#b91c1c',
        default => '#64748b',
    };
@endphp

<style>
    .task-list-tab-project { position: relative; }
    .task-list-tab-project-add.btn-ghost { position: absolute; right: 4px; top: 50%; transform: translateY(-50%); border: none; padding: 4px 6px; font-size: 12px; color: inherit; }
    .task-list-tab-project.is-active .task-list-tab-project-add.btn-ghost { color: #fff; }

    /* Kartu tugas: border kiri = warna prioritas (--accent), hover ngangkat dikit biar kerasa
    "bisa diklik/diseret" tanpa perlu badge warna-warni penuh di dalamnya. */
    .task-card { position: relative; background: var(--surface-alt); border: 1px solid var(--border); border-left: 3px solid var(--accent, var(--border)); border-radius: 10px; padding: 10px 12px; cursor: pointer; box-shadow: 0 1px 2px rgba(15,23,42,0.05); transition: box-shadow 0.15s ease, transform 0.15s ease; }
    .task-card:hover { box-shadow: 0 8px 20px rgba(15,23,42,0.12); transform: translateY(-1px); }
    html.dark .task-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.35); }
    .task-card-top { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 7px; }
    .task-card-title { font-size: 13px; font-weight: 600; color: var(--text-heading); line-height: 1.4; margin: 0; flex: 1; min-width: 0; }
    .task-card-avatar { flex-shrink: 0; margin-top: 1px; }
    .task-card-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 11px; color: var(--text-muted); margin-bottom: 6px; }
    .task-card-priority { display: inline-flex; align-items: center; gap: 5px; font-weight: 600; color: var(--accent); }
    .task-card-priority .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); flex-shrink: 0; }
    .task-card-assignee { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--text-muted); }
    .task-card-footer { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border-soft); font-size: 11px; color: var(--text-muted); }

    /* Jumlah kolom/section (List custom atau status Todo/Proses/dst) ganjil = 3 kolom per baris,
    genap = 2 kolom (--cols diisi dari Blade). Di layar sempit dipaksa 1 kolom - grid banyak
    kolom bikin section kesempitan buat dibaca di HP. */
    .task-board-grid { display: grid; grid-template-columns: repeat(var(--cols, 1), 1fr); gap: 12px; align-items: start; }
    @media (max-width: 639.98px) { .task-board-grid { grid-template-columns: 1fr; } }

    /* Gaya "hero" khusus Todo Saya: gradient sama seperti .page-hero yang sudah ada di
    app.css (bukan warna baru), plus shape dekoratif .a-deco-* yang sama dipakai di
    welcome-card/absen-card. Card di dalamnya jadi kotak putih polos ala mockup Trello. */
    .task-hero-column { position: relative; overflow: hidden; border-radius: 20px; padding: 16px 14px; color: #fff; }
    .task-hero-column.navy { background: linear-gradient(130deg, var(--brand-fill) 0%, color-mix(in srgb, var(--brand-fill), #ffffff 22%) 100%); }
    .task-hero-column.gold { background: linear-gradient(130deg, #92400e 0%, #d97706 100%); }
    .task-hero-column.green { background: linear-gradient(130deg, #047C54 0%, #024732 100%); }
    /* Sama seperti gradient aktif tab gold-shiny di halaman profil (#f5c518 -> #d4a017),
    cuma digelapin dikit biar kebaca beda dari aksen "profil saya" itu sendiri. */
    .task-hero-column.gold-dark { background: linear-gradient(135deg, #d4a017 0%, #92720a 100%); }
    .task-hero-column-header { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 14px; font-weight: 700; font-size: 14px; }
    .task-hero-column-header .count-badge { background: rgba(255,255,255,0.25); color: #fff; font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 99px; }
    .task-hero-column-body { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 10px; }
    .task-hero-column .task-card { background: #fff; border: none; box-shadow: 0 3px 10px rgba(0,0,0,0.18); }
    .task-hero-column .task-card-title { color: #1e293b; }
    .task-hero-column .task-card-meta, .task-hero-column .task-card-assignee, .task-hero-column .task-card-footer { color: #64748b; }
    .task-hero-column .task-card-footer { border-top-color: #e2e8f0; }
    .task-hero-column .task-card .btn-ghost { color: #1e293b; }
    .task-hero-column .task-card select.form-input { background: #f8fafc; border-color: #e2e8f0; color: #1e293b; }
    .task-hero-column .task-card p[style*="italic"] { color: #64748b !important; }
    .task-hero-column-body > p { color: rgba(255,255,255,0.75) !important; }

    /* Modal Task: overlay jangan ikut scroll (dobel scrollbar keliatan aneh), cukup .modal-box
    sendiri yang scroll di dalam container-nya. Modal detail dilebarin (2026-08-02), berlaku
    buat semua jenis task ke depannya karena dipakai bareng di 1 modal detail generik. */
    .modal-overlay { overflow-y: hidden; }
    .task-detail-modal-wide { max-width: 1040px; }
    .task-create-modal-wide { max-width: 640px; }
    .task-checklist-checkbox.custom-checkbox { width: 22px; height: 22px; }
    .task-checklist-checkbox .box { border-radius: 6px; }
    .task-checklist-checkbox .box i { font-size: 12px; }

    /* Modal detail task di HP: layar penuh (bukan kotak ngambang kekecilan), 2 kolom
    (isi task + komentar) ditumpuk vertikal, komentar dapat garis atas bukan garis kiri. */
    @media (max-width: 767.98px) {
        .task-detail-modal-wide,
        .task-create-modal-wide {
            max-width: 100%;
            width: 100%;
            height: 100%;
            max-height: 100%;
            margin: 0;
            border-radius: 0;
        }
        .task-detail-body { flex-direction: column !important; gap: 20px !important; }
        .task-detail-comments {
            flex: 1 1 auto !important;
            border-left: none !important;
            border-top: 1px solid var(--border-soft);
            padding-left: 0 !important;
            padding-top: 16px;
        }
    }
</style>

<x-page-header title="Tugas" subtitle="" />

<div>
    {{-- Reuse .section-nav / .section-nav-btn (pola pill nav yang sudah dipakai di modul lain)
    biar konsisten sama app, bukan CSS tab custom. Project dapat slot terpisah (dropdown Board). --}}
    <div class="section-nav">
        <button type="button" wire:click="selectScope('todo')" class="section-nav-btn navy {{ ! $activeProjectId && $scope === 'todo' ? 'is-active' : '' }}"><i class="fa-solid fa-user-check"></i> Todo Saya</button>
        <button type="button" wire:click="selectScope('saya')" class="section-nav-btn green {{ ! $activeProjectId && $scope === 'saya' ? 'is-active' : '' }}"><i class="fa-solid fa-list-check"></i> Tugas Saya</button>
        @if ($this->hasSubordinates)
            <button type="button" wire:click="selectScope('individu')" class="section-nav-btn teal {{ ! $activeProjectId && $scope === 'individu' ? 'is-active' : '' }}"><i class="fa-solid fa-users"></i> Penugasan Individu</button>
            <button type="button" wire:click="selectScope('tim')" class="section-nav-btn rust {{ ! $activeProjectId && $scope === 'tim' ? 'is-active' : '' }}"><i class="fa-solid fa-sitemap"></i> Tim Saya</button>
        @endif
        <div class="section-nav-btn gold task-list-tab-project {{ $activeProjectId ? 'is-active' : '' }}" x-data="{ open: false }" @click.outside="open = false" style="padding-right:34px;">
            <button type="button" @click="open = ! open" style="all:unset;display:flex;align-items:center;gap:7px;cursor:pointer;max-width:44vw;">
                <i class="fa-solid fa-diagram-project"></i>
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $this->activeProject->name ?? 'Board' }}</span>
                <i class="fa-solid fa-chevron-down" style="font-size:10px;flex-shrink:0;"></i>
            </button>
            <button type="button" wire:click="openCreateProjectModal" class="btn-ghost task-list-tab-project-add" title="Board Baru" onclick="event.stopPropagation()"><i class="fa-solid fa-plus"></i></button>

            <div x-show="open" x-cloak @click.stop style="position:absolute;top:100%;left:0;margin-top:6px;background:var(--surface);border-radius:12px;box-shadow:0 8px 30px rgba(4,44,108,0.12),0 0 0 1px rgba(4,44,108,0.06);padding:10px;min-width:240px;z-index:220;">
                <div wire:ignore>
                    <select data-tom-select data-placeholder="Cari Board..." wire:change="selectProject($event.target.value)">
                        <option value="">Tanpa Board</option>
                        @foreach ($this->myProjects as $project)
                            <option value="{{ $project->id }}" @selected($activeProjectId === $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Konten --}}
    <div>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;display:flex;align-items:center;gap:8px;">
                @if ($this->activeProject)
                    <span style="width:10px;height:10px;border-radius:50%;background:{{ $this->activeProject->color }};"></span>
                    {{ $this->activeProject->name }}
                @else
                    {{ ['todo' => 'Todo Saya', 'saya' => 'Tugas Saya', 'individu' => 'Penugasan Individu', 'tim' => 'Tim Saya'][$scope] ?? 'Tugas' }}
                @endif
            </h3>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                @if (! $activeProjectId && $scope === 'individu')
                    <div style="position:relative;flex:1 1 200px;min-width:160px;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:11px;color:var(--text-faint);"></i>
                        <input type="text" wire:model.live.debounce.400ms="search" class="form-input" placeholder="Cari nama tugas / pegawai..." style="padding-left:30px;font-size:12.5px;width:100%;">
                    </div>
                @endif
                @if (! $activeProjectId && in_array($scope, ['individu', 'saya'], true))
                    <div
                        x-data="{ secondsLeft: 10 }"
                        x-init="setInterval(() => { secondsLeft = secondsLeft <= 1 ? 10 : secondsLeft - 1; }, 1000)"
                        wire:poll.10s="$refresh"
                        style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--text-faint);"
                        title="Board refresh otomatis tiap 10 detik"
                    >
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span x-text="secondsLeft + 'd'"></span>
                    </div>
                @endif
                @if ($this->activeProject)
                    <button type="button" wire:click="openMembersModal" class="btn-ghost btn-sm"><i class="fa-solid fa-users"></i> Anggota ({{ $this->projectMembers->count() }})</button>
                    @if ($this->canManageProjectMembers)
                        <button type="button" wire:click="deleteProject" wire:confirm="Hapus/nonaktifkan Board &quot;{{ $this->activeProject->name }}&quot;? Board akan hilang dari daftar semua anggota - task di dalamnya tidak ikut terhapus, tapi jadi tidak bisa diakses lagi." class="btn-ghost btn-sm" style="color:var(--danger-text);" title="Hapus/Nonaktifkan Board">
                            <i class="fa-solid fa-box-archive"></i> Hapus Board
                        </button>
                    @endif
                @endif
                @if ($this->activeProject || ! in_array($scope, ['tim', 'saya'], true))
                    <button type="button" wire:click="openCreateTask" class="btn-primary btn-sm"><i class="fa-solid fa-plus" style="font-size:12px;"></i> Tugas Baru</button>
                @endif
            </div>
        </div>

        @if ($isTableScope)
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
                                    <span class="badge {{ $badgeClass(TaskStatus::tryFrom($task->status)?->getColor() ?? 'gray') }}">{{ TaskStatus::tryFrom($task->status)?->getLabel() ?? $task->status }}</span>
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
        @else
            @php $sectionCount = $this->activeProject ? $this->activeProject->lists->count() : count($boardStatuses); @endphp
            <div class="task-board-grid" style="background:var(--surface-page);border-radius:16px;padding:14px;border:1px solid var(--border-soft);--cols:{{ $sectionCount === 0 ? 1 : ($sectionCount % 2 === 0 ? 2 : 3) }};">
                @if ($this->activeProject)
                    @foreach ($this->activeProject->lists as $list)
                        @php $listAccent = $list->color ?: ($list->is_final ? 'var(--brand-success)' : 'var(--text-muted)'); @endphp
                        <div style="background:var(--surface);border-radius:12px;overflow:hidden;border:1px solid var(--border-soft);">
                            <div style="background:var(--surface-alt);border-left:4px solid {{ $listAccent }};padding:10px 14px;display:flex;align-items:center;gap:6px;">
                                @if ($editingListId === $list->id)
                                    <input type="text" wire:model="editingListName" wire:keydown.enter="renameList" wire:keydown.escape="cancelEditList" class="form-input" style="font-size:12px;padding:3px 6px;height:auto;flex:1;" autofocus>
                                    <button type="button" wire:click="renameList" style="background:none;border:none;color:var(--brand-success);cursor:pointer;"><i class="fa-solid fa-check"></i></button>
                                    <button type="button" wire:click="cancelEditList" style="background:none;border:none;color:var(--text-faint);cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                                @else
                                    <span
                                        style="color:var(--text-heading);font-weight:700;font-size:13.5px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;{{ $this->canManageProjectMembers ? 'cursor:pointer;' : '' }}"
                                        @if ($this->canManageProjectMembers) wire:click="startEditList('{{ $list->id }}')" @endif
                                    >
                                        {{ $list->name }} @if ($list->is_final) <i class="fa-solid fa-flag-checkered" style="color:var(--brand-success);" title="List Selesai"></i> @endif
                                    </span>
                                    <span style="background:var(--border-soft);color:var(--text-muted);font-size:11px;font-weight:700;padding:2px 9px;border-radius:99px;">{{ count($this->tasksByList[$list->id] ?? []) }}</span>
                                    @if ($this->canManageProjectMembers)
                                        <div class="dropdown">
                                            <button type="button" style="background:none;border:none;color:var(--text-muted);cursor:pointer;padding:2px 4px;"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                            <div class="dropdown-menu dropdown-menu-right">
                                                <button type="button" wire:click="moveListLeft('{{ $list->id }}')" class="dropdown-item"><i class="fa-solid fa-arrow-up"></i> Naik</button>
                                                <button type="button" wire:click="moveListRight('{{ $list->id }}')" class="dropdown-item"><i class="fa-solid fa-arrow-down"></i> Turun</button>
                                                <label class="dropdown-item" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                                    <i class="fa-solid fa-palette"></i> Ubah Warna
                                                    <input type="color" value="{{ $list->color ?: ($list->is_final ? '#047C54' : '#475569') }}" onchange="$wire.changeListColor('{{ $list->id }}', $event.target.value)" style="margin-left:auto;width:22px;height:22px;padding:0;border:none;cursor:pointer;">
                                                </label>
                                                @unless ($list->is_final)
                                                    <button type="button" wire:click="markListFinal('{{ $list->id }}')" class="dropdown-item"><i class="fa-solid fa-flag-checkered"></i> Tandai Selesai</button>
                                                @endunless
                                                <button type="button" wire:click="deleteList('{{ $list->id }}')" wire:confirm="Hapus List ini? Pastikan sudah kosong." class="dropdown-item" style="color:var(--danger-text);"><i class="fa-solid fa-trash"></i> Hapus List</button>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>
                            <div style="padding:10px;display:flex;flex-direction:column;gap:8px;">
                                @forelse (($this->tasksByList[$list->id] ?? []) as $task)
                                    <div class="task-card" wire:click="openTaskDetail('{{ $task->id }}')" style="--accent:{{ $priorityAccent($task->priority?->getColor() ?? 'gray') }};">
                                        <div class="task-card-top">
                                            <p class="task-card-title">{{ \Illuminate\Support\Str::limit($task->requirement, 80) }}</p>
                                            @if ($task->assignedTo && ! $task->is_self_assigned)
                                                <span class="task-card-avatar"><x-avatar :user="$task->assignedTo" :size="22" /></span>
                                            @endif
                                        </div>
                                        <div class="task-card-meta">
                                            <span class="task-card-priority"><span class="dot"></span> {{ $task->priority?->getLabel() ?? $task->priority }}</span>
                                            @if ($task->checklistItems->isNotEmpty())
                                                <span><i class="fa-solid fa-square-check"></i> {{ $task->checklistItems->where('is_done', true)->count() }}/{{ $task->checklistItems->count() }}</span>
                                            @endif
                                            @if ($task->due_date)
                                                <span><i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $fmtDate($task->due_date) }}</span>
                                            @endif
                                        </div>
                                        @if ($task->assignedTo && ! $task->is_self_assigned)
                                            <div class="task-card-assignee">{{ $task->assignedTo->profile?->nickname ?: $task->assignedTo->name }}</div>
                                        @endif
                                        @if ($this->activeProject->lists->count() > 1)
                                            <select wire:change="moveTaskToList('{{ $task->id }}', $event.target.value)" onclick="event.stopPropagation()" class="form-input" style="font-size:11px;padding:4px 6px;height:auto;margin-top:8px;">
                                                <option value="" selected disabled>Pindah ke...</option>
                                                @foreach ($this->activeProject->lists as $target)
                                                    @continue($target->id === $list->id)
                                                    <option value="{{ $target->id }}">{{ $target->name }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <div class="task-card-footer">
                                            <span><i class="fa-solid fa-paperclip"></i> {{ $task->attachments_count }}</span>
                                            <span><i class="fa-regular fa-comment"></i> {{ $task->comments->count() }}</span>
                                            @if ($task->rating)
                                                <span><i class="fa-solid fa-star" style="color:#f5a524;"></i> {{ $task->rating }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p style="font-size:12px;color:var(--text-muted);text-align:center;padding:12px 0;margin:0;">Kosong</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                    @if ($this->canManageProjectMembers)
                        <div style="background:var(--surface);border-radius:12px;padding:10px;border:1px solid var(--border-soft);">
                            <div style="display:flex;gap:6px;">
                                <input type="color" wire:model="newListColor" style="width:32px;height:32px;padding:0;border:none;border-radius:6px;cursor:pointer;flex-shrink:0;">
                                <input type="text" class="form-input" style="font-size:12.5px;" wire:model="newListName" wire:keydown.enter="createList" placeholder="Nama List baru...">
                                <button type="button" wire:click="createList" class="btn-ghost btn-sm"><i class="fa-solid fa-plus"></i></button>
                            </div>
                        </div>
                    @endif
                @else
                    @foreach ($boardStatuses as $status)
                        @php
                            $statusAccent = match ($status->getColor()) {
                                'warning' => '#a16207',
                                'info' => 'var(--brand)',
                                'success' => 'var(--brand-success)',
                                'danger' => '#b91c1c',
                                default => 'var(--text-muted)',
                            };
                        @endphp
                        @if ($useBoardHero)
                            <div class="task-hero-column {{ $heroClassFor($status) }}">
                                <span class="a-deco-1"></span>
                                <span class="a-deco-3"></span>
                                <div class="task-hero-column-header">
                                    <span>{{ $status->getLabel() }}</span>
                                    <span class="count-badge">{{ count($this->tasksByStatus[$status->value] ?? []) }}</span>
                                </div>
                                <div class="task-hero-column-body">
                        @else
                        <div style="background:var(--surface);border-radius:12px;overflow:hidden;border:1px solid var(--border-soft);">
                            <div style="background:var(--surface-alt);border-left:4px solid {{ $statusAccent }};padding:10px 14px;display:flex;align-items:center;justify-content:space-between;">
                                <span style="color:var(--text-heading);font-weight:700;font-size:13.5px;">{{ $status->getLabel() }}</span>
                                <span style="background:var(--border-soft);color:var(--text-muted);font-size:11px;font-weight:700;padding:2px 9px;border-radius:99px;">{{ count($this->tasksByStatus[$status->value] ?? []) }}</span>
                            </div>
                            <div style="padding:10px;display:flex;flex-direction:column;gap:8px;">
                        @endif
                                @forelse (($this->tasksByStatus[$status->value] ?? []) as $task)
                                    <div class="task-card" wire:click="openTaskDetail('{{ $task->id }}')" style="--accent:{{ $priorityAccent($task->priority?->getColor() ?? 'gray') }};">
                                        <div class="task-card-top">
                                            <p class="task-card-title">{{ \Illuminate\Support\Str::limit($task->requirement, 80) }}</p>
                                            @if ($task->assignedTo && ! $task->is_self_assigned)
                                                <span class="task-card-avatar"><x-avatar :user="$task->assignedTo" :size="22" /></span>
                                            @endif
                                        </div>
                                        <div class="task-card-meta">
                                            <span class="task-card-priority"><span class="dot"></span> {{ $task->priority?->getLabel() ?? $task->priority }}</span>
                                            @if ($task->checklistItems->isNotEmpty())
                                                <span><i class="fa-solid fa-square-check"></i> {{ $task->checklistItems->where('is_done', true)->count() }}/{{ $task->checklistItems->count() }}</span>
                                            @endif
                                            @if ($task->due_date)
                                                <span><i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $fmtDate($task->due_date) }}</span>
                                            @endif
                                        </div>
                                        @if ($task->assignedTo && ! $task->is_self_assigned)
                                            <div class="task-card-assignee">{{ $task->assignedTo->profile?->nickname ?: $task->assignedTo->name }}</div>
                                        @endif
                                        @if ($task->status === \App\Enum\Task\TaskStatus::REVIEW->value)
                                            {{-- Di REVIEW cuma creator yang bisa mutusin: kembalikan buat direvisi, atau selesaikan. --}}
                                            @if ($task->created_by === auth()->id())
                                                <select wire:change="moveTaskStatus('{{ $task->id }}', $event.target.value)" onclick="event.stopPropagation()" class="form-input" style="font-size:11px;padding:4px 6px;height:auto;margin-top:8px;">
                                                    <option value="" selected disabled>Ubah ke...</option>
                                                    <option value="{{ \App\Enum\Task\TaskStatus::IN_PROGRESS->value }}">Sedang Dalam Proses</option>
                                                    <option value="{{ \App\Enum\Task\TaskStatus::COMPLETE->value }}">Selesai</option>
                                                </select>
                                            @else
                                                <p style="font-size:11px;color:var(--text-muted);margin:8px 0 0;font-style:italic;">Menunggu penilaian pembuat tugas.</p>
                                            @endif
                                        @elseif ($task->status !== \App\Enum\Task\TaskStatus::COMPLETE->value && ($task->status !== \App\Enum\Task\TaskStatus::TODO->value || $task->assigned_to === auth()->id()))
                                            @php
                                                $order = ($task->is_self_assigned || ! $task->needs_review) ? \App\Enum\Task\TaskStatus::BOARD_ORDER_SELF_ASSIGNED : \App\Enum\Task\TaskStatus::BOARD_ORDER;
                                                $nextStatus = $order[array_search(\App\Enum\Task\TaskStatus::from($task->status), $order, true) + 1] ?? null;
                                            @endphp
                                            @if ($nextStatus)
                                                <button type="button" wire:click="moveTaskStatus('{{ $task->id }}', '{{ $nextStatus->value }}')" onclick="event.stopPropagation()" class="btn-ghost btn-sm" style="margin-top:8px;width:100%;">
                                                    Pindah ke {{ $nextStatus->getLabel() }} <i class="fa-solid fa-arrow-right" style="font-size:10px;"></i>
                                                </button>
                                            @endif
                                        @endif

                                        @unless ($task->is_self_assigned)
                                            <div class="task-card-footer">
                                                <span><i class="fa-solid fa-paperclip"></i> {{ $task->attachments_count }}</span>
                                                <span><i class="fa-regular fa-comment"></i> {{ $task->comments->count() }}</span>
                                                @if ($task->rating)
                                                    <span><i class="fa-solid fa-star" style="color:#f5a524;"></i> {{ $task->rating }}</span>
                                                @endif
                                            </div>
                                        @endunless
                                    </div>
                                @empty
                                    <p style="font-size:12px;color:var(--text-muted);text-align:center;padding:12px 0;margin:0;">Kosong</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        @endif

        @if ($this->historyTasks)
            <div x-data="{ open: false }" style="margin-top:24px;">
                <button type="button" @click="open = ! open" class="btn-ghost btn-sm">
                    <i class="fa-solid fa-clock-rotate-left"></i> Riwayat
                    <i class="fa-solid fa-chevron-down" :style="open && 'transform:rotate(180deg);'" style="font-size:10px;transition:transform 0.15s;"></i>
                </button>
                <div x-show="open" x-cloak style="margin-top:12px;">
                    <div style="overflow-x:auto;background:var(--surface);border-radius:12px;border:1px solid var(--border);">
                        <table style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr style="text-align:left;font-size:11px;color:var(--text-muted);border-bottom:1px solid var(--border);">
                                    <th style="padding:10px 14px;">Kode</th>
                                    <th style="padding:10px 14px;">Kebutuhan</th>
                                    <th style="padding:10px 14px;">Prioritas</th>
                                    <th style="padding:10px 14px;">Ditugaskan</th>
                                    <th style="padding:10px 14px;">Selesai</th>
                                    <th style="padding:10px 14px;">Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->historyTasks as $task)
                                    <tr wire:click="openTaskDetail('{{ $task->id }}')" style="cursor:pointer;border-bottom:1px solid var(--border);font-size:13px;">
                                        <td style="padding:10px 14px;font-weight:600;">{{ $task->code }}</td>
                                        <td style="padding:10px 14px;">{{ \Illuminate\Support\Str::limit($task->requirement, 60) }}</td>
                                        <td style="padding:10px 14px;">{{ $task->priority?->getLabel() ?? $task->priority }}</td>
                                        <td style="padding:10px 14px;">{{ $task->assignedTo?->name ?? '-' }}</td>
                                        <td style="padding:10px 14px;">{{ $fmtDate($task->completed_date, 'j M Y') ?? '-' }}</td>
                                        <td style="padding:10px 14px;">
                                            @if ($task->rating)
                                                <i class="fa-solid fa-star" style="color:#f5a524;"></i> {{ $task->rating }}
                                            @else
                                                <span style="color:var(--text-faint);">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" style="padding:20px;text-align:center;color:var(--text-muted);">Belum ada riwayat.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:12px;">{{ $this->historyTasks->links() }}</div>
                </div>
            </div>
        @endif
    </div>
</div>

@if ($showTaskModal)
    <div class="modal-overlay" wire:click="closeTaskModal">
        <div class="modal-box task-create-modal-wide" onclick="event.stopPropagation()">
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

                @if ($this->activeProject)
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

                    <div>
                        <label class="form-label">Reviewer (yang berhak kasih rating)</label>
                        <div wire:ignore>
                            <select data-tom-select data-placeholder="Saya sendiri (pembuat tugas)" wire:model="reviewerId">
                                <option value="">Saya sendiri (pembuat tugas)</option>
                                @foreach ($this->projectMembers as $member)
                                    @continue($member->user_id === auth()->id())
                                    <option value="{{ $member->user_id }}">{{ $member->user?->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Kalau diisi, cuma orang ini yang bisa kasih rating begitu tugas selesai - bukan cuma kamu sebagai pembuat.</p>
                    </div>

                    <div style="display:flex;align-items:center;gap:10px;">
                        <label class="custom-checkbox" style="--accent:#042c6c;">
                            <input type="checkbox" wire:model="notifyOnComplete">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <span style="font-size:13px;">Kasih tahu aku kalau kartu ini masuk List "Selesai"</span>
                    </div>
                @elseif ($this->hasSubordinates && $scope !== 'todo')
                    <div>
                        <label class="form-label">Tugaskan ke *</label>
                        <div wire:ignore>
                            <select data-tom-select data-placeholder="-- Pilih Pegawai --" wire:model="assigned_to">
                                <option value="">-- Pilih Pegawai --</option>
                                @foreach ($this->subordinates as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('assigned_to') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div style="display:flex;align-items:center;gap:10px;">
                        <label class="custom-checkbox" style="--accent:#042c6c;">
                            <input type="checkbox" wire:model.live="needsReview">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                        <span style="font-size:13px;">Butuh Approval Saya <span style="color:var(--text-muted);">— minta ditinjau dulu sebelum beneran selesai</span></span>
                    </div>

                    @if ($needsReview)
                        <div style="display:flex;align-items:center;gap:10px;padding-left:29px;">
                            <label class="custom-checkbox" style="--accent:#042c6c;">
                                <input type="checkbox" wire:model="notifyOnReview">
                                <span class="box"><i class="fa-solid fa-check"></i></span>
                            </label>
                            <span style="font-size:12.5px;color:var(--text-muted);">Kasih tahu aku kalau pelaksana mengajukan review</span>
                        </div>
                    @else
                        <div style="display:flex;align-items:center;gap:10px;padding-left:29px;">
                            <label class="custom-checkbox" style="--accent:#042c6c;">
                                <input type="checkbox" wire:model="notifyOnComplete">
                                <span class="box"><i class="fa-solid fa-check"></i></span>
                            </label>
                            <span style="font-size:12.5px;color:var(--text-muted);">Kasih tahu aku kalau pelaksana sudah selesai menjalankan tugas</span>
                        </div>
                    @endif
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

@if ($showEditTaskModal)
    <div class="modal-overlay" wire:click="closeEditTaskModal">
        <div class="modal-box task-create-modal-wide" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Edit Tugas</h3>
            <form wire:submit="updateTaskDetails" style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label class="form-label">Kebutuhan / Deskripsi *</label>
                    <textarea class="form-input" rows="3" wire:model="editRequirement"></textarea>
                    @error('editRequirement') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>

                <div class="date-grid">
                    <div>
                        <label class="form-label">Tenggat</label>
                        <input type="date" class="form-input" wire:model="editDueDate">
                    </div>
                    <div>
                        <label class="form-label">Prioritas</label>
                        <select class="form-input" wire:model="editPriority">
                            @foreach (TaskPriority::cases() as $p)
                                <option value="{{ $p->value }}">{{ $p->getLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" wire:click="closeEditTaskModal" class="btn-ghost">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($this->selectedTask)
    @php
        $task = $this->selectedTask;
        $canEdit = $task->status !== \App\Enum\Task\TaskStatus::COMPLETE->value
            && ($task->is_self_assigned ? $task->assigned_to === auth()->id() : $task->created_by === auth()->id());
        $canDelete = $task->is_self_assigned
            ? $task->assigned_to === auth()->id()
            : ($task->created_by === auth()->id() && in_array($task->status, [\App\Enum\Task\TaskStatus::TODO->value, \App\Enum\Task\TaskStatus::IN_PROGRESS->value], true));
        $canRevertComplete = ! $task->is_self_assigned && ! $task->needs_review
            && $task->status === \App\Enum\Task\TaskStatus::COMPLETE->value
            && $task->created_by === auth()->id()
            && $task->completed_date
            && \Carbon\Carbon::parse($task->completed_date)->gt(now()->subDay());
    @endphp
    <div class="modal-overlay" wire:click="closeTaskDetail">
        <div class="modal-box task-detail-modal-wide" onclick="event.stopPropagation()">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:16px;">
                <h3 style="font-size:22px;font-weight:700;color:var(--text-heading);margin:0;">{{ $task->code }}</h3>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    @if ($canRevertComplete)
                        <button type="button" wire:click="moveTaskStatus('{{ $task->id }}', '{{ \App\Enum\Task\TaskStatus::IN_PROGRESS->value }}')" class="btn-ghost btn-sm" title="Kembalikan ke Sedang Dalam Proses (masih < 24 jam)"><i class="fa-solid fa-rotate-left"></i> Kembalikan</button>
                    @endif
                    @if ($canEdit)
                        <button type="button" wire:click="openEditTask('{{ $task->id }}')" class="btn-ghost" style="padding:6px 10px;" title="Edit"><i class="fa-solid fa-pen"></i></button>
                    @endif
                    @if ($canDelete)
                        <button type="button" wire:click="deleteTask('{{ $task->id }}')" wire:confirm="Hapus tugas ini? Ngga bisa dibalikin lagi." class="btn-ghost" style="padding:6px 10px;color:var(--danger-text);" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                    @endif
                    <button type="button" wire:click="closeTaskDetail" class="btn-ghost" style="padding:6px 10px;"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>

            <div class="task-detail-body" style="{{ $task->is_self_assigned ? '' : 'display:flex;gap:24px;' }}">
                <div style="flex:1;min-width:0;">
                    <p style="font-size:16px;color:var(--text-heading);margin:0 0 14px;">{{ $task->requirement }}</p>

                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;font-size:13.5px;">
                        <span class="badge {{ $badgeClass(TaskStatus::tryFrom($task->status)?->getColor() ?? 'gray') }}">{{ TaskStatus::tryFrom($task->status)?->getLabel() ?? $task->status }}</span>
                        <span class="badge {{ $badgeClass($task->priority?->getColor() ?? 'gray') }}">{{ $task->priority?->getLabel() ?? $task->priority }}</span>
                        @if ($task->due_date)<span class="badge badge-cancelled">Tenggat: {{ $fmtDate($task->due_date, 'j M Y') }}</span>@endif
                        @if ($task->project)<span class="badge badge-cancelled"><i class="fa-solid fa-diagram-project"></i> {{ $task->project->name }}</span>@endif
                    </div>

                    @unless ($task->is_self_assigned)
                        <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:18px;">
                            Dibuat oleh {{ $task->createdBy?->name ?? '-' }}
                            @if ($task->assignedTo) &middot; ditugaskan ke {{ $task->assignedTo->name }} @endif
                            @if ($task->reviewer_id) &middot; reviewer {{ $task->reviewer?->name }} @endif
                        </div>

                        @if ($task->status === \App\Enum\Task\TaskStatus::COMPLETE->value)
                            <div style="margin-bottom:18px;">
                                @if ($task->rating)
                                    <div style="display:flex;align-items:center;gap:4px;">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i class="fa-solid fa-star" style="font-size:15px;color:{{ $i <= $task->rating ? '#f5a524' : 'var(--border-soft)' }};"></i>
                                        @endfor
                                        <span style="font-size:12px;color:var(--text-muted);margin-left:4px;">({{ $task->rating }}/5)</span>
                                    </div>
                                @elseif (($task->reviewer_id ?? $task->created_by) === auth()->id())
                                    <div x-data="{ hover: 0 }">
                                        <p style="font-size:12.5px;font-weight:600;margin:0 0 6px;">Kasih rating buat pelaksana <span style="color:var(--text-muted);font-weight:400;">(opsional)</span></p>
                                        <div style="display:flex;gap:4px;">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <button type="button" wire:click="rateTask('{{ $task->id }}', {{ $i }})" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0" style="background:none;border:none;cursor:pointer;padding:2px;">
                                                    <i class="fa-solid fa-star" :style="hover >= {{ $i }} ? 'color:#f5a524;' : 'color:var(--border-soft);'" style="font-size:18px;"></i>
                                                </button>
                                            @endfor
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endunless

                    @if ($task->assigned_to === auth()->id() && $task->rejected_reason)
                        {{-- Catatan penolakan — cuma kelihatan buat pelaksana, bukan komentar publik. --}}
                        <div style="background:var(--danger-soft-bg);border:1px solid var(--danger-soft-border);border-radius:12px;padding:14px 16px;margin-bottom:18px;">
                            <p style="font-size:14px;font-weight:700;color:var(--danger-soft-text);margin:0 0 4px;">
                                <i class="fa-solid fa-triangle-exclamation"></i> Tinjauan Ditolak
                                @if ($task->rejectionNoteBy) oleh {{ $task->rejectionNoteBy->name }} @endif
                            </p>
                            <p style="font-size:14.5px;color:var(--danger-soft-text);margin:0;">{{ $task->rejected_reason }}</p>
                        </div>
                    @endif

                    <div style="margin-bottom:18px;">
                        <h4 style="font-size:16px;font-weight:700;margin:0 0 10px;">Checklist</h4>
                        @php
                            $isTaskCreator = auth()->id() === $task->created_by;
                            $isTaskAssignee = auth()->id() === $task->assigned_to;
                            $canToggleChecklist = $isTaskCreator || $isTaskAssignee;
                            // Pemberi tugas boleh edit/hapus item siapa pun. Pelaksana (bukan
                            // sekaligus pemberi tugas) cuma boleh edit/hapus item yang dia buat
                            // sendiri - tidak boleh utak-atik item bikinan pemberi tugas.
                            $canModifyItem = fn ($item) => $isTaskCreator || ($isTaskAssignee && ($item->created_by ?? $task->created_by) === auth()->id());
                        @endphp
                        @forelse ($task->checklistItems as $item)
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                                @if ($editingChecklistItemId === $item->id)
                                    <input type="text" wire:model="editingChecklistItemTitle" wire:keydown.enter="updateChecklistItem" wire:keydown.escape="cancelEditChecklistItem" class="form-input" style="font-size:13px;padding:4px 8px;height:auto;flex:1;" autofocus>
                                    <button type="button" wire:click="updateChecklistItem" style="background:none;border:none;color:var(--brand-success);cursor:pointer;"><i class="fa-solid fa-check"></i></button>
                                    <button type="button" wire:click="cancelEditChecklistItem" style="background:none;border:none;color:var(--text-faint);cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                                @else
                                    <label style="display:flex;align-items:center;gap:10px;font-size:15px;flex:1;min-width:0;{{ $canToggleChecklist ? '' : 'opacity:0.6;cursor:not-allowed;' }}">
                                        <span class="custom-checkbox task-checklist-checkbox" style="--accent:#042c6c;">
                                            <input type="checkbox" @if ($canToggleChecklist) wire:click="toggleChecklistItem('{{ $item->id }}')" @else disabled @endif @checked($item->is_done)>
                                            <span class="box"><i class="fa-solid fa-check"></i></span>
                                        </span>
                                        <span style="{{ $item->is_done ? 'text-decoration:line-through;color:var(--text-muted);' : '' }}">{{ $item->title }}</span>
                                    </label>
                                    @if ($canModifyItem($item))
                                        <button type="button" wire:click="startEditChecklistItem('{{ $item->id }}', @js($item->title))" style="background:none;border:none;color:var(--text-faint);cursor:pointer;padding:2px;" title="Ubah"><i class="fa-solid fa-pen" style="font-size:11px;"></i></button>
                                        <button type="button" wire:click="deleteChecklistItem('{{ $item->id }}')" wire:confirm="Hapus item checklist ini?" style="background:none;border:none;color:var(--text-faint);cursor:pointer;padding:2px;" title="Hapus"><i class="fa-solid fa-trash" style="font-size:11px;"></i></button>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <p style="font-size:14px;color:var(--text-muted);">Tidak ada checklist.</p>
                        @endforelse
                        @if ($canToggleChecklist)
                            <div style="display:flex;gap:6px;margin-top:8px;">
                                <input type="text" class="form-input" style="font-size:12.5px;" wire:model="newChecklistTitle" wire:keydown.enter.prevent="addChecklistItemToTask('{{ $task->id }}')" placeholder="Tambah item checklist...">
                                <button type="button" wire:click="addChecklistItemToTask('{{ $task->id }}')" class="btn-ghost btn-sm">Tambah</button>
                            </div>
                        @endif
                    </div>

                    <div x-data="{ showAddAttachment: false }" style="margin-bottom:18px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <h4 style="font-size:16px;font-weight:700;margin:0;"><i class="fa-solid fa-paperclip"></i> Lampiran</h4>
                            <button type="button" @click="showAddAttachment = !showAddAttachment" class="btn-ghost btn-sm">Tambah</button>
                        </div>

                        <div x-show="showAddAttachment" x-cloak style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:14px;padding:12px;background:var(--surface-alt);border-radius:10px;">
                            <input type="file" wire:model="newAttachmentFile" style="font-size:11.5px;max-width:180px;">
                            <button type="button" wire:click="uploadFileAttachment" wire:loading.attr="disabled" wire:target="newAttachmentFile,uploadFileAttachment" class="btn-ghost" style="padding:5px 10px;font-size:11.5px;">Unggah</button>
                            <input type="url" class="form-input" style="max-width:180px;font-size:11.5px;padding:6px 8px;" wire:model="newAttachmentLink" placeholder="https://...">
                            <input type="text" class="form-input" style="max-width:140px;font-size:11.5px;padding:6px 8px;" wire:model="newAttachmentLinkLabel" placeholder="Judul link (opsional)">
                            <button type="button" wire:click="addLinkAttachment" class="btn-ghost" style="padding:5px 10px;font-size:11.5px;">Tambah Link</button>
                            @error('newAttachmentFile') <p style="width:100%;font-size:11px;color:var(--danger-text);margin:4px 0 0;">{{ $message }}</p> @enderror
                            @error('newAttachmentLink') <p style="width:100%;font-size:11px;color:var(--danger-text);margin:4px 0 0;">{{ $message }}</p> @enderror
                        </div>

                        @if ($task->attachments->isNotEmpty())
                            <p style="font-size:12px;font-weight:700;color:var(--text-muted);margin:0 0 8px;">Files</p>
                        @endif

                        <div style="display:flex;flex-direction:column;gap:8px;">
                            @forelse ($task->attachments as $attachment)
                                @php
                                    $isImage = ! $attachment->isLink() && preg_match('/\.(jpe?g|png|gif|webp)$/i', $attachment->file_path ?? '');
                                    $ext = $attachment->isLink() ? 'LINK' : strtoupper(pathinfo($attachment->file_path ?? '', PATHINFO_EXTENSION) ?: 'FILE');
                                @endphp
                                <div x-data="{ open: false }" style="display:flex;align-items:center;gap:12px;padding:8px;border-radius:10px;background:var(--surface-alt);">
                                    @if ($isImage)
                                        <img src="{{ $attachment->file_url }}" @click="open = true" style="width:48px;height:48px;object-fit:cover;border-radius:8px;cursor:pointer;flex-shrink:0;" alt="{{ $attachment->file_name }}">
                                        <div x-show="open" x-cloak @click="open = false" style="position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:10000;display:flex;align-items:center;justify-content:center;padding:24px;">
                                            <img src="{{ $attachment->file_url }}" style="max-width:90vw;max-height:90vh;border-radius:8px;object-fit:contain;" @click.stop alt="{{ $attachment->file_name }}">
                                        </div>
                                    @else
                                        <span style="width:48px;height:48px;flex-shrink:0;border-radius:8px;background:var(--border-soft);color:var(--text-muted);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">{{ $ext }}</span>
                                    @endif

                                    <div style="flex:1;min-width:0;">
                                        @if ($attachment->isLink())
                                            <a href="{{ $attachment->url }}" target="_blank" rel="noopener" style="display:block;font-size:13.5px;font-weight:600;color:#1a56c4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $attachment->file_name ?? $attachment->url }}</a>
                                        @else
                                            <a href="{{ $attachment->file_url }}" target="_blank" rel="noopener" style="display:block;font-size:13.5px;font-weight:600;color:var(--text-heading);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $attachment->file_name ?? 'File' }}</a>
                                        @endif
                                        <span style="font-size:11.5px;color:var(--text-muted);">Ditambahkan {{ $attachment->created_at->diffForHumans() }}</span>
                                    </div>

                                    @unless ($attachment->isLink())
                                        <a href="{{ $attachment->file_url }}" target="_blank" rel="noopener" class="btn-ghost" style="padding:6px 8px;flex-shrink:0;" title="Buka"><i class="fa-solid fa-up-right-from-square"></i></a>
                                    @endunless
                                    <button type="button" wire:click="removeAttachment('{{ $attachment->id }}')" wire:confirm="Hapus lampiran ini?" class="btn-ghost" style="padding:6px 8px;flex-shrink:0;color:var(--danger-text);" title="Hapus"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                            @empty
                                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada lampiran.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                @unless ($task->is_self_assigned)
                    {{-- Komentar ala Trello, kolom kanan terpisah — cuma buat task yang bukan self-assigned. --}}
                    <div class="task-detail-comments" style="flex:0 0 400px;border-left:1px solid var(--border-soft);padding-left:24px;">
                        <h4 style="font-size:16px;font-weight:700;margin:0 0 12px;"><i class="fa-regular fa-comment"></i> Komentar ({{ $task->comments->count() }})</h4>
                        <div style="display:flex;gap:8px;margin-bottom:14px;">
                            <input type="text" class="form-input" style="font-size:13.5px;" wire:model="newComment" wire:keydown.enter.prevent="addComment('{{ $task->id }}')" placeholder="Tulis komentar...">
                            <button type="button" wire:click="addComment('{{ $task->id }}')" class="btn-primary" style="font-size:13.5px;padding:8px 14px;">Kirim</button>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:12px;max-height:420px;overflow-y:auto;">
                            @forelse ($task->comments as $comment)
                                <div style="font-size:13.5px;">
                                    <span style="font-weight:700;">{{ $comment->user?->name }}</span>
                                    <span style="color:var(--text-muted);"> &middot; {{ $comment->created_at->diffForHumans() }}</span>
                                    <p style="margin:2px 0 0;">{{ $comment->comment }}</p>
                                </div>
                            @empty
                                <p style="font-size:13.5px;color:var(--text-muted);margin:0;">Belum ada komentar.</p>
                            @endforelse
                        </div>
                    </div>
                @endunless
            </div>
        </div>
    </div>
@endif


@if ($showProjectModal)
    <div class="modal-overlay" wire:click="closeProjectModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Board Baru</h3>
            <form wire:submit="createProject" style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label class="form-label">Nama Board *</label>
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
                    <button type="submit" class="btn-primary">Buat Board</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($showMembersModal && $this->activeProject)
    <div class="modal-overlay" wire:click="closeMembersModal">
        <div class="modal-box" style="height:90vh;max-height:90vh;display:flex;flex-direction:column;" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-shrink:0;">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">Anggota {{ $this->activeProject->name }}</h3>
                <button type="button" wire:click="closeMembersModal" class="btn-ghost" style="padding:4px 8px;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;flex:1;overflow-y:auto;min-height:0;">
                @foreach ($this->projectMembers as $member)
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <x-avatar :user="$member->user" :size="20" />
                            <span style="font-size:13px;">{{ $member->user?->name }}</span>
                            @if ($member->role === 'owner')
                                <span class="badge badge-approved">Owner</span>
                            @elseif ($member->is_assigner)
                                <span class="badge badge-info">Assigner</span>
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
                <div style="display:flex;gap:6px;align-items:flex-start;flex-shrink:0;">
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
