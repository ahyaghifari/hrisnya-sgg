<div class="form-page-wrap">
    <div class="page-hero violet">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('sikartap.dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Dashboard Sikartap</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Hasil Radiologi</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Hasil Radiologi</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">{{ $this->sikartap->user->name }}</p>
        </div>
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:22px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:12px;">
            <div><label class="form-label">Jenis Pemeriksaan</label><input type="text" wire:model="form.jenis_pemeriksaan" class="form-input" placeholder="Thorax PA"></div>
            <div>
                <label class="form-label">Foto Thorax</label>
                <select wire:model="form.foto_thorax" class="form-input">
                    <option value="">-- Pilih --</option>
                    <option value="Normal">Normal</option>
                    <option value="Abnormal">Abnormal</option>
                </select>
                @error('form.foto_thorax') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
            </div>
        </div>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;">Deskripsi</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:20px;">
            <div><label class="form-label">Jantung</label><input type="text" wire:model="form.jantung" class="form-input"></div>
            <div><label class="form-label">Paru Kanan</label><input type="text" wire:model="form.paru_kanan" class="form-input"></div>
            <div><label class="form-label">Paru Kiri</label><input type="text" wire:model="form.paru_kiri" class="form-input"></div>
            <div><label class="form-label">Diafragma</label><input type="text" wire:model="form.diafragma" class="form-input"></div>
            <div><label class="form-label">Tulang</label><input type="text" wire:model="form.tulang" class="form-input"></div>
        </div>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">Kesimpulan</h3>
        <textarea wire:model="form.kesimpulan" class="form-input" rows="3" style="margin-bottom:20px;"></textarea>

        @if ($this->sikartap->mcuRadiologyResult?->file_path)
            <p style="font-size:12px;margin-bottom:8px;">
                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($this->sikartap->mcuRadiologyResult->file_path) }}" target="_blank">📥 Lihat berkas hasil radiologi saat ini</a>
            </p>
        @endif

        <label class="upload-zone" style="display:block;margin-bottom:12px;">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:32px;color:var(--text-faint);margin-bottom:10px;display:block;"></i>
            <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 4px;">
                {{ $file ? $file->getClientOriginalName() : 'Klik untuk upload berkas hasil radiologi' }}
            </p>
            <p style="font-size:12px;color:var(--text-muted);margin:0;">PDF, JPG, PNG &middot; Maks. 10MB</p>
            <input type="file" style="display:none;" accept=".pdf,.jpg,.jpeg,.png" wire:model="file">
        </label>
        @error('file') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror

        <button type="button" wire:click="save" class="btn-primary">💾 Simpan Data</button>
    </div>
</div>
