<div>
<style>
    .task-menu-section-label { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em; margin: 0 0 10px; }
    .task-menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 26px; }
    .task-menu-tile { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; padding: 16px; border-radius: 14px; background: var(--surface); border: 1px solid var(--border); text-decoration: none; cursor: pointer; transition: box-shadow 0.15s ease, transform 0.15s ease; position: relative; }
    .task-menu-tile:hover { box-shadow: 0 8px 20px rgba(15,23,42,0.1); transform: translateY(-1px); }
    html.dark .task-menu-tile:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.35); }
    .task-menu-tile i.task-menu-tile-icon { font-size: 18px; }
    .task-menu-tile-label { font-size: 13.5px; font-weight: 700; color: var(--text-heading); }
    .task-menu-tile-count { position: absolute; top: 12px; right: 12px; display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 99px; font-size: 10.5px; font-weight: 800; background: #b45309; color: #fff; }
    .task-menu-divider { height: 1px; background: var(--border-soft); margin: 4px 0 22px; }
    /* Tile Board: avatar anggota bertumpuk di bagian bawah (tumpang tindih, cincin warna latar tile). */
    .task-menu-tile-board { min-height: 112px; }
    .task-menu-avatars { display: flex; align-items: center; margin-top: auto; padding-left: 6px; }
    .task-menu-avatars > * { margin-left: -6px; box-shadow: 0 0 0 2px var(--surface); }
    .task-menu-avatars-more { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px; padding: 0 6px; border-radius: 99px; background: var(--border-soft); color: var(--text-muted); font-size: 10.5px; font-weight: 800; }

    /* Tile "Tugas" (Todo/Saya/Individu/Tim) - inline (icon + label sebaris, bukan icon di atas
       label kayak tile Board), border aksen kiri ikut warna icon masing-masing. Font lebih
       kecil di mobile, tetap inline di desktop (bukan cuma di mobile). Warna aksen dilewatkan
       lewat custom property --tile-accent (bukan inline style color langsung) supaya hover di
       sini bisa nimpa warna icon/label - inline style color biasa tidak bisa ditimpa CSS class
       tanpa !important, custom property bisa. */
    .task-menu-tile-inline { flex-direction: row; align-items: center; gap: 10px; padding: 28px 14px; border-color: rgba(15,23,42,0.16); border-left-width: 4px; border-left-style: solid; border-left-color: var(--tile-accent); transition: box-shadow 0.15s ease, transform 0.15s ease, background-color 0.15s ease; }
    html.dark .task-menu-tile-inline { border-color: rgba(255,255,255,0.24); border-left-color: var(--tile-accent); }
    /* Warna aksen per tile, dengan varian terang di dark mode (navy/cokelat tua hampir tak
       terlihat di latar gelap - border kiri & icon jadi hilang). */
    .tile-todo { --tile-accent: var(--brand-fill); }
    .tile-saya { --tile-accent: #d4a017; }
    .tile-individu { --tile-accent: #0d9488; }
    .tile-tim { --tile-accent: #b45309; }
    html.dark .tile-todo { --tile-accent: #60a5fa; }
    html.dark .tile-saya { --tile-accent: #fbbf24; }
    html.dark .tile-individu { --tile-accent: #2dd4bf; }
    html.dark .tile-tim { --tile-accent: #fb923c; }
    .task-menu-tile-inline .task-menu-tile-icon { font-size: 15px; color: var(--tile-accent); }
    .task-menu-tile-inline .task-menu-tile-label { font-size: 12.5px; transition: color 0.15s ease; }
    .task-menu-tile-inline:hover { background-color: var(--tile-accent); }
    .task-menu-tile-inline:hover .task-menu-tile-icon,
    .task-menu-tile-inline:hover .task-menu-tile-label { color: #fff; }
    /* Aksen dark mode terang -> teks putih di atasnya kurang kontras saat hover, pakai teks gelap. */
    html.dark .task-menu-tile-inline:hover .task-menu-tile-icon,
    html.dark .task-menu-tile-inline:hover .task-menu-tile-label { color: #0f172a; }
    @media (max-width: 640px) {
        .task-menu-tile-inline { padding: 22px 12px; gap: 8px; }
        .task-menu-tile-inline .task-menu-tile-icon { font-size: 13px; }
        .task-menu-tile-inline .task-menu-tile-label { font-size: 11.5px; }
    }
    @media (min-width: 641px) {
        .task-menu-tile-inline { padding: 32px 20px; gap: 14px; }
        .task-menu-tile-inline .task-menu-tile-icon { font-size: 22px; }
        .task-menu-tile-inline .task-menu-tile-label { font-size: 16px; }
        /* auto-fill (dipakai .task-menu-grid biasa) bikin kolom kosong kalau tile-nya cuma
           sedikit (4 tile Tugas) - sisa lebar container jadi nganggur di kanan. auto-fit
           collapse kolom kosong itu & tile yang ADA melar isi sisa ruang, jadi leluasa
           di layar lebar. */
        .task-menu-grid-tugas { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    }
    /* Sama dengan ukuran desktop tombol "Tugas Baru" di papan. */
    @media (min-width: 768px) {
        .task-menu-new-btn { padding: 11px 22px !important; font-size: 14.5px !important; border-radius: 10px !important; }
        .task-menu-new-btn i { font-size: 14px !important; }
    }
    /* Transisi buka modal "Board Baru" - sama dengan modal di halaman papan. */
    @keyframes task-modal-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes task-modal-pop { from { opacity: 0; transform: translateY(14px) scale(0.97); } to { opacity: 1; transform: none; } }
    @keyframes task-modal-slide { from { transform: translateY(100%); } to { transform: none; } }
    .modal-overlay { animation: task-modal-fade 0.18s ease-out; }
    .modal-overlay .modal-box { border-radius: 20px; }
    .modal-overlay > .modal-box { animation: task-modal-pop 0.22s cubic-bezier(0.2, 0.8, 0.2, 1); }
    @media (max-width: 767.98px) { .modal-overlay > .modal-box { animation: task-modal-slide 0.25s cubic-bezier(0.2, 0.8, 0.2, 1); } }
    @media (prefers-reduced-motion: reduce) { .modal-overlay, .modal-overlay > .modal-box { animation: none; } }
</style>

<x-page-header title="Tugas" subtitle="" />

<p class="task-menu-section-label">Tugas</p>
<div class="task-menu-grid task-menu-grid-tugas">
    <a href="{{ route('tugas.papan', 'todo') }}" wire:navigate class="task-menu-tile task-menu-tile-inline tile-todo">
        <i class="fa-solid fa-user-check task-menu-tile-icon"></i>
        <span class="task-menu-tile-label">Todo Saya</span>
    </a>
    <a href="{{ route('tugas.papan', 'saya') }}" wire:navigate class="task-menu-tile task-menu-tile-inline tile-saya">
        @if ($this->myTaskPendingCount > 0)
            <span class="task-menu-tile-count">{{ $this->myTaskPendingCount }}</span>
        @endif
        <i class="fa-solid fa-list-check task-menu-tile-icon"></i>
        <span class="task-menu-tile-label">Tugas Saya</span>
    </a>
    @if ($this->hasSubordinates)
        <a href="{{ route('tugas.papan', 'individu') }}" wire:navigate class="task-menu-tile task-menu-tile-inline tile-individu">
            <i class="fa-solid fa-users task-menu-tile-icon"></i>
            <span class="task-menu-tile-label">Penugasan Individu</span>
        </a>
        <a href="{{ route('tugas.papan', 'tim') }}" wire:navigate class="task-menu-tile task-menu-tile-inline tile-tim">
            <i class="fa-solid fa-sitemap task-menu-tile-icon"></i>
            <span class="task-menu-tile-label">Tim Saya</span>
        </a>
    @endif
</div>

<div class="task-menu-divider"></div>

<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
    <p class="task-menu-section-label" style="margin:0;">Board</p>
    <div style="display:flex;align-items:center;gap:8px;">
        <a href="{{ route('tugas.arsip') }}" wire:navigate class="btn-ghost btn-sm task-menu-new-btn"><i class="fa-solid fa-box-archive" style="font-size:12px;"></i> Lihat Arsip</a>
        <button type="button" wire:click="openCreateProjectModal" class="btn-primary btn-sm task-menu-new-btn"><i class="fa-solid fa-plus" style="font-size:12px;"></i> Board Baru</button>
    </div>
</div>
<div class="task-menu-grid">
    @foreach ($this->myProjects as $project)
        @php
            // Avatar anggota bertumpuk di bawah tile - dibatasi 6, sisanya "+N".
            $memberUsers = $project->members->pluck('user')->filter();
            $shownMembers = $memberUsers->take(6);
            $moreMembers = $memberUsers->count() - $shownMembers->count();
        @endphp
        <a href="{{ route('tugas.papan.board', $project) }}" wire:navigate class="task-menu-tile task-menu-tile-board">
            <i class="fa-solid fa-diagram-project task-menu-tile-icon" style="color:{{ $project->color }};"></i>
            <span class="task-menu-tile-label">{{ $project->name }}</span>
            @if ($shownMembers->isNotEmpty())
                <span class="task-menu-avatars" title="{{ $memberUsers->count() }} anggota">
                    @foreach ($shownMembers as $memberUser)
                        <x-avatar :user="$memberUser" :size="24" />
                    @endforeach
                    @if ($moreMembers > 0)
                        <span class="task-menu-avatars-more">+{{ $moreMembers }}</span>
                    @endif
                </span>
            @endif
        </a>
    @endforeach
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
