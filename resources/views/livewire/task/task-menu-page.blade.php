<style>
    .task-menu-section-label { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em; margin: 0 0 10px; }
    .task-menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 26px; }
    .task-menu-tile { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; padding: 16px; border-radius: 14px; background: var(--surface); border: 1px solid var(--border); text-decoration: none; cursor: pointer; transition: box-shadow 0.15s ease, transform 0.15s ease; position: relative; }
    .task-menu-tile:hover { box-shadow: 0 8px 20px rgba(15,23,42,0.1); transform: translateY(-1px); }
    html.dark .task-menu-tile:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.35); }
    .task-menu-tile i.task-menu-tile-icon { font-size: 18px; }
    .task-menu-tile-label { font-size: 13.5px; font-weight: 700; color: var(--text-heading); }
    .task-menu-tile-count { position: absolute; top: 12px; right: 12px; display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 99px; font-size: 10.5px; font-weight: 800; background: #b45309; color: #fff; }
    .task-menu-tile-add { align-items: center; justify-content: center; color: var(--text-muted); border-style: dashed; }
    .task-menu-tile-add i { font-size: 18px; }
    .task-menu-divider { height: 1px; background: var(--border-soft); margin: 4px 0 22px; }
</style>

<div>
<x-page-header title="Tugas" subtitle="" />

<p class="task-menu-section-label">Tim/Project</p>
<div class="task-menu-grid">
    @foreach ($this->teamBoards as $project)
        <a href="{{ route('tugas.papan.board', $project) }}" wire:navigate class="task-menu-tile">
            <i class="fa-solid fa-diagram-project task-menu-tile-icon" style="color:{{ $project->color }};"></i>
            <span class="task-menu-tile-label">{{ $project->name }}</span>
        </a>
    @endforeach
    <button type="button" wire:click="openCreateProjectModal" class="task-menu-tile task-menu-tile-add">
        <i class="fa-solid fa-plus"></i>
        <span class="task-menu-tile-label">Board Baru</span>
    </button>
</div>

<div class="task-menu-divider"></div>

<p class="task-menu-section-label">Tugas</p>
<div class="task-menu-grid">
    <a href="{{ route('tugas.papan', 'todo') }}" wire:navigate class="task-menu-tile">
        <i class="fa-solid fa-user-check task-menu-tile-icon" style="color:var(--brand-fill);"></i>
        <span class="task-menu-tile-label">Todo Saya</span>
    </a>
    <a href="{{ route('tugas.papan', 'saya') }}" wire:navigate class="task-menu-tile">
        @if ($this->myTaskPendingCount > 0)
            <span class="task-menu-tile-count">{{ $this->myTaskPendingCount }}</span>
        @endif
        <i class="fa-solid fa-list-check task-menu-tile-icon" style="color:#d4a017;"></i>
        <span class="task-menu-tile-label">Tugas Saya</span>
    </a>
    @if ($this->hasSubordinates)
        <a href="{{ route('tugas.papan', 'individu') }}" wire:navigate class="task-menu-tile">
            <i class="fa-solid fa-users task-menu-tile-icon" style="color:#0d9488;"></i>
            <span class="task-menu-tile-label">Penugasan Individu</span>
        </a>
        <a href="{{ route('tugas.papan', 'tim') }}" wire:navigate class="task-menu-tile">
            <i class="fa-solid fa-sitemap task-menu-tile-icon" style="color:#b45309;"></i>
            <span class="task-menu-tile-label">Tim Saya</span>
        </a>
    @endif
</div>

<p class="task-menu-section-label">Board Saya</p>
<div class="task-menu-grid">
    @foreach ($this->personalBoards as $project)
        <a href="{{ route('tugas.papan.board', $project) }}" wire:navigate class="task-menu-tile">
            <i class="fa-solid fa-diagram-project task-menu-tile-icon" style="color:{{ $project->color }};"></i>
            <span class="task-menu-tile-label">{{ $project->name }}</span>
        </a>
    @endforeach
    <button type="button" wire:click="openCreateProjectModal" class="task-menu-tile task-menu-tile-add">
        <i class="fa-solid fa-plus"></i>
        <span class="task-menu-tile-label">Board Baru</span>
    </button>
</div>

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
</div>
