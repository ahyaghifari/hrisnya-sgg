<div class="form-page-wrap">
    <div class="page-hero">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('kanban') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Kanban</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Board Baru</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Board Baru</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Buat board untuk kelola task tim/project</p>
        </div>
    </div>

    <form wire:submit="submit">
        <div class="form-card">
            <div style="display:flex;flex-direction:column;gap:18px;">
                <div>
                    <label class="form-label">Nama Board <span style="color:var(--danger-text);">*</span></label>
                    <input type="text" class="form-input" placeholder="Contoh: Rekrutmen Q3 2026" wire:model="name">
                    @error('name') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-input" rows="3" placeholder="Deskripsi singkat board ini (opsional)" wire:model="description"></textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <a href="{{ route('kanban') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-plus" style="font-size:12px;"></i> Buat Board</button>
        </div>
    </form>
</div>
