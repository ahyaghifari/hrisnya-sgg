<div class="form-page-wrap">
    <div class="page-hero violet">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Pemeriksaan Dokter</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">{{ $this->sikartap->user->name }}</p>
        </div>
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:22px;margin-bottom:16px;">
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:16px;">Referensi hasil pemeriksaan klinis lain (read-only):</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;font-size:12px;">
            <div style="padding:10px;border-radius:8px;background:var(--surface-alt,rgba(0,0,0,.03));">
                <strong>Vital Sign / Perawat</strong><br>
                @if ($this->sikartap->mcuNurseAssessment)
                    TD {{ $this->sikartap->mcuNurseAssessment->tekanan_darah }} · Nadi {{ $this->sikartap->mcuNurseAssessment->nadi }}/mnt · EKG {{ $this->sikartap->mcuNurseAssessment->hasil_ekg ?? '-' }}
                @else
                    <span style="color:var(--text-faint);">belum diisi</span>
                @endif
            </div>
            <div style="padding:10px;border-radius:8px;background:var(--surface-alt,rgba(0,0,0,.03));">
                <strong>Laboratorium</strong><br>
                @if ($this->sikartap->mcuLabResult)
                    HBsAg {{ $this->sikartap->mcuLabResult->hbsag ?? '-' }} · HIV {{ $this->sikartap->mcuLabResult->hiv ?? '-' }}
                @else
                    <span style="color:var(--text-faint);">belum diisi</span>
                @endif
            </div>
            <div style="padding:10px;border-radius:8px;background:var(--surface-alt,rgba(0,0,0,.03));">
                <strong>Radiologi</strong><br>
                @if ($this->sikartap->mcuRadiologyResult)
                    Foto Thorax {{ $this->sikartap->mcuRadiologyResult->foto_thorax ?? '-' }}
                @else
                    <span style="color:var(--text-faint);">belum diisi</span>
                @endif
            </div>
        </div>
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:22px;">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;">Pemeriksaan Fisik</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:20px;">
            <div><label class="form-label">Kulit</label><input type="text" wire:model="form.kulit" class="form-input"></div>
            <div><label class="form-label">Rambut</label><input type="text" wire:model="form.rambut" class="form-input"></div>
            <div><label class="form-label">Visus Mata</label><input type="text" wire:model="form.visus_mata" class="form-input"></div>
            <div><label class="form-label">Buta Warna</label><input type="text" wire:model="form.buta_warna" class="form-input"></div>
            <div><label class="form-label">Lapang Pandang</label><input type="text" wire:model="form.lapang_pandang" class="form-input"></div>
            <div><label class="form-label">Hidung</label><input type="text" wire:model="form.hidung" class="form-input"></div>
            <div><label class="form-label">Telinga</label><input type="text" wire:model="form.telinga" class="form-input"></div>
            <div><label class="form-label">Tonsil</label><input type="text" wire:model="form.tonsil" class="form-input"></div>
            <div><label class="form-label">Faring</label><input type="text" wire:model="form.faring" class="form-input"></div>
            <div><label class="form-label">Gigi</label><input type="text" wire:model="form.gigi" class="form-input"></div>
            <div><label class="form-label">Kelenjar Gondok</label><input type="text" wire:model="form.kelenjar_gondok" class="form-input"></div>
        </div>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;">Paru &amp; Jantung</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:20px;">
            <div><label class="form-label">Suara Pernafasan</label><input type="text" wire:model="form.suara_pernafasan" class="form-input"></div>
            <div><label class="form-label">Ronchi</label><input type="text" wire:model="form.ronchi" class="form-input"></div>
            <div><label class="form-label">Wheezing</label><input type="text" wire:model="form.wheezing" class="form-input"></div>
            <div><label class="form-label">Lainnya (Paru)</label><input type="text" wire:model="form.lainnya_paru" class="form-input"></div>
            <div><label class="form-label">Jantung</label><input type="text" wire:model="form.jantung" class="form-input"></div>
        </div>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;">Abdomen &amp; Lainnya</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:20px;">
            <div><label class="form-label">Hati</label><input type="text" wire:model="form.hati" class="form-input"></div>
            <div><label class="form-label">Limpa</label><input type="text" wire:model="form.limpa" class="form-input"></div>
            <div><label class="form-label">Epigastrium</label><input type="text" wire:model="form.epigastrium" class="form-input"></div>
            <div><label class="form-label">Ginjal</label><input type="text" wire:model="form.ginjal" class="form-input"></div>
            <div><label class="form-label">Lainnya (Abdomen)</label><input type="text" wire:model="form.lainnya_abdomen" class="form-input"></div>
            <div><label class="form-label">Ekstremitas</label><input type="text" wire:model="form.ekstremitas" class="form-input"></div>
            <div><label class="form-label">Neurologi</label><input type="text" wire:model="form.neurologi" class="form-input"></div>
        </div>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">Diagnosa</h3>
        <textarea wire:model="form.diagnosa" class="form-input" rows="2" style="margin-bottom:20px;"></textarea>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">Rekomendasi</h3>
        <textarea wire:model="form.rekomendasi" class="form-input" rows="2" style="margin-bottom:20px;" placeholder="mis. Fit untuk bekerja / Perlu evaluasi lanjutan"></textarea>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">Catatan</h3>
        <textarea wire:model="form.catatan" class="form-input" rows="3" style="margin-bottom:20px;"></textarea>

        <button type="button" wire:click="save" class="btn-primary">💾 Simpan Hasil Pemeriksaan</button>
    </div>
</div>
