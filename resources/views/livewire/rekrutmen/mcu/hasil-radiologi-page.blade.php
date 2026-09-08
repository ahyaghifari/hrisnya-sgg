@php $nama = $this->invitee->jobApplication->answersMap()['full_name'] ?? '—'; @endphp
<div class="form-page-wrap">
    <div class="page-hero" style="background:linear-gradient(130deg,#6e561f 0%,#98782d 100%);">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Hasil Radiologi</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">{{ $nama }}</p>
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

        <button type="button" wire:click="save" class="btn-primary">💾 Simpan Data</button>
    </div>
</div>
