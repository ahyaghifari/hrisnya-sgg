<div class="form-page-wrap">
    <div class="page-hero violet">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('sikartap.dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Dashboard Sikartap</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Hasil Laboratorium</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Hasil Laboratorium</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">{{ $this->sikartap->user->name }}</p>
        </div>
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:22px;">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;">Hematologi</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:20px;">
            <div><label class="form-label">Hemoglobin</label><input type="text" wire:model="form.hemoglobin" class="form-input"></div>
            <div><label class="form-label">Leukosit</label><input type="text" wire:model="form.leukosit" class="form-input"></div>
            <div><label class="form-label">Eritrosit</label><input type="text" wire:model="form.eritrosit" class="form-input"></div>
            <div><label class="form-label">Trombosit</label><input type="text" wire:model="form.trombosit" class="form-input"></div>
            <div><label class="form-label">Hematokrit</label><input type="text" wire:model="form.hematokrit" class="form-input"></div>
            <div><label class="form-label">MCV</label><input type="text" wire:model="form.mcv" class="form-input"></div>
            <div><label class="form-label">MCH</label><input type="text" wire:model="form.mch" class="form-input"></div>
            <div><label class="form-label">MCHC</label><input type="text" wire:model="form.mchc" class="form-input"></div>
        </div>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;">Hitung Jenis Leukosit</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:20px;">
            <div><label class="form-label">Basofil</label><input type="text" wire:model="form.basofil" class="form-input"></div>
            <div><label class="form-label">Eosinofil</label><input type="text" wire:model="form.eosinofil" class="form-input"></div>
            <div><label class="form-label">Stab</label><input type="text" wire:model="form.stab" class="form-input"></div>
            <div><label class="form-label">Segmen</label><input type="text" wire:model="form.segmen" class="form-input"></div>
            <div><label class="form-label">Limfosit</label><input type="text" wire:model="form.limfosit" class="form-input"></div>
            <div><label class="form-label">Monosit</label><input type="text" wire:model="form.monosit" class="form-input"></div>
        </div>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;">Kimia Darah &amp; Serologi</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:12px;">
            <div><label class="form-label">GDS</label><input type="text" wire:model="form.gds" class="form-input"></div>
            <div><label class="form-label">GDA</label><input type="text" wire:model="form.gda" class="form-input"></div>
            <div>
                <label class="form-label">HBsAg</label>
                <select wire:model="form.hbsag" class="form-input">
                    <option value="">-- Pilih --</option>
                    <option value="Reaktif">Reaktif</option>
                    <option value="Non Reaktif">Non Reaktif</option>
                </select>
                @error('form.hbsag') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">HIV</label>
                <select wire:model="form.hiv" class="form-input">
                    <option value="">-- Pilih --</option>
                    <option value="Reaktif">Reaktif</option>
                    <option value="Non Reaktif">Non Reaktif</option>
                </select>
                @error('form.hiv') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
            </div>
        </div>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">Kesimpulan</h3>
        <textarea wire:model="form.kesimpulan" class="form-input" rows="3" style="margin-bottom:20px;"></textarea>

        @if ($this->sikartap->mcuLabResult?->file_path)
            <p style="font-size:12px;margin-bottom:8px;">
                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($this->sikartap->mcuLabResult->file_path) }}" target="_blank">📥 Lihat berkas hasil lab saat ini</a>
            </p>
        @endif

        <label class="upload-zone" style="display:block;margin-bottom:12px;">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:32px;color:var(--text-faint);margin-bottom:10px;display:block;"></i>
            <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 4px;">
                {{ $file ? $file->getClientOriginalName() : 'Klik untuk upload berkas hasil lab' }}
            </p>
            <p style="font-size:12px;color:var(--text-muted);margin:0;">PDF, JPG, PNG &middot; Maks. 10MB</p>
            <input type="file" style="display:none;" accept=".pdf,.jpg,.jpeg,.png" wire:model="file">
        </label>
        @error('file') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror

        <button type="button" wire:click="save" class="btn-primary">💾 Simpan Data</button>
    </div>
</div>
