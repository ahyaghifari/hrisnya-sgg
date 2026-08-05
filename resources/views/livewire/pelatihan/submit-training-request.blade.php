<div class="form-page-wrap">
    <div class="page-hero green">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('pelatihan') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Pelatihan</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Pengajuan Pelatihan</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Pengajuan Pelatihan</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Ajukan pelatihan di luar yang diadakan perusahaan (mis. pelatihan eksternal/seminar)</p>
        </div>
    </div>

    <form wire:submit="submit">
        <div class="form-card">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-circle-info" style="color:var(--brand);font-size:12px;"></i></div>
                <span class="section-title">Detail Pelatihan</span>
            </div>

            <div style="margin-bottom:12px;">
                <label class="form-label">Judul Pelatihan <span style="color:var(--danger-text);">*</span></label>
                <input type="text" class="form-input" wire:model="title" placeholder="Mis. Workshop Manajemen Rumah Sakit">
                @error('title') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div style="margin-bottom:12px;">
                <label class="form-label">Penyelenggara</label>
                <input type="text" class="form-input" wire:model="organizer" placeholder="Mis. PERSI, Kemenkes, dll.">
                @error('organizer') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div class="col2-grid" style="margin-bottom:12px;">
                <div>
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-input" wire:model="start_date">
                    @error('start_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Tanggal Selesai</label>
                    <input type="date" class="form-input" wire:model="end_date">
                    @error('end_date') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label class="form-label">Estimasi Biaya (Rp)</label>
                <input type="number" step="0.01" min="0" class="form-input" wire:model="estimated_cost" placeholder="0">
                @error('estimated_cost') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Deskripsi/Alasan</label>
                <textarea class="form-input" rows="4" placeholder="Jelaskan tujuan/manfaat pelatihan ini..." wire:model="description"></textarea>
                @error('description') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <a href="{{ route('pelatihan.pengajuan-saya') }}" wire:navigate class="btn-ghost"><i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Kembali</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="submit"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Ajukan Pelatihan</button>
        </div>
    </form>
</div>
