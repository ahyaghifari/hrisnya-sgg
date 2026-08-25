<div class="form-page-wrap">
    <div class="page-hero violet">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('sikartap.dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Dashboard Sikartap</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Asesmen Perawat</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Asesmen Perawat &amp; Hasil EKG</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">{{ $this->sikartap->user->name }}</p>
        </div>
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:22px;">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;">Vital Sign</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:20px;">
            <div><label class="form-label">Tinggi Badan (cm)</label><input type="number" step="0.1" wire:model="form.tinggi_badan" class="form-input"></div>
            <div><label class="form-label">Berat Badan (kg)</label><input type="number" step="0.1" wire:model="form.berat_badan" class="form-input"></div>
            <div><label class="form-label">Tekanan Darah (mmHg)</label><input type="text" wire:model="form.tekanan_darah" class="form-input" placeholder="120/80"></div>
            <div><label class="form-label">Nadi (x/menit)</label><input type="number" wire:model="form.nadi" class="form-input"></div>
            <div><label class="form-label">Suhu (°C)</label><input type="number" step="0.1" wire:model="form.suhu" class="form-input"></div>
            <div><label class="form-label">Pernafasan (x/menit)</label><input type="number" wire:model="form.pernafasan" class="form-input"></div>
        </div>
        @foreach (['tinggi_badan','berat_badan','tekanan_darah','nadi','suhu','pernafasan'] as $f)
            @error("form.{$f}") <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
        @endforeach

        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;">Keluhan Kesehatan</h3>
        <textarea wire:model="form.keluhan_kesehatan" class="form-input" rows="2" style="margin-bottom:20px;"></textarea>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">Riwayat Penyakit Keluarga</h3>
        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:8px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><span class="custom-checkbox"><input type="checkbox" wire:model="form.rpk_hipertensi"><span class="box"><i class="fa-solid fa-check"></i></span></span> Hipertensi</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><span class="custom-checkbox"><input type="checkbox" wire:model="form.rpk_stroke"><span class="box"><i class="fa-solid fa-check"></i></span></span> Stroke</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><span class="custom-checkbox"><input type="checkbox" wire:model="form.rpk_jantung"><span class="box"><i class="fa-solid fa-check"></i></span></span> Jantung</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><span class="custom-checkbox"><input type="checkbox" wire:model="form.rpk_diabetes"><span class="box"><i class="fa-solid fa-check"></i></span></span> Diabetes</label>
        </div>
        <input type="text" wire:model="form.rpk_lainnya" class="form-input" placeholder="Lainnya" style="margin-bottom:20px;">

        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">Riwayat Penyakit Dahulu</h3>
        <textarea wire:model="form.riwayat_penyakit_dahulu" class="form-input" rows="2" style="margin-bottom:20px;"></textarea>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">Riwayat Operasi</h3>
        <textarea wire:model="form.riwayat_operasi" class="form-input" rows="2" style="margin-bottom:20px;"></textarea>

        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">Riwayat Alergi</h3>
        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:8px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><span class="custom-checkbox"><input type="checkbox" wire:model="form.alergi_makanan"><span class="box"><i class="fa-solid fa-check"></i></span></span> Makanan</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><span class="custom-checkbox"><input type="checkbox" wire:model="form.alergi_obat"><span class="box"><i class="fa-solid fa-check"></i></span></span> Obat-obatan</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><span class="custom-checkbox"><input type="checkbox" wire:model="form.alergi_debu"><span class="box"><i class="fa-solid fa-check"></i></span></span> Debu</label>
        </div>
        <input type="text" wire:model="form.alergi_lainnya" class="form-input" placeholder="Lainnya" style="margin-bottom:20px;">

        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">Riwayat Kebiasaan</h3>
        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:8px;">
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><span class="custom-checkbox"><input type="checkbox" wire:model="form.kebiasaan_olahraga"><span class="box"><i class="fa-solid fa-check"></i></span></span> Olahraga</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><span class="custom-checkbox"><input type="checkbox" wire:model="form.kebiasaan_merokok"><span class="box"><i class="fa-solid fa-check"></i></span></span> Merokok</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><span class="custom-checkbox"><input type="checkbox" wire:model="form.kebiasaan_alkohol"><span class="box"><i class="fa-solid fa-check"></i></span></span> Minum Alkohol</label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><span class="custom-checkbox"><input type="checkbox" wire:model="form.kebiasaan_obat"><span class="box"><i class="fa-solid fa-check"></i></span></span> Konsumsi Obat-obatan</label>
        </div>
        <input type="text" wire:model="form.kebiasaan_lainnya" class="form-input" placeholder="Lainnya" style="margin-bottom:20px;">

        <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;">Hasil EKG</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:12px;">
            <div>
                <label class="form-label">Hasil EKG</label>
                <select wire:model="form.hasil_ekg" class="form-input">
                    <option value="">-- Pilih --</option>
                    <option value="Normal">Normal</option>
                    <option value="Abnormal">Abnormal</option>
                </select>
                @error('form.hasil_ekg') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror
            </div>
        </div>
        <textarea wire:model="form.keterangan_ekg" class="form-input" rows="2" placeholder="Keterangan EKG" style="margin-bottom:12px;"></textarea>

        @if ($this->sikartap->mcuNurseAssessment?->file_ekg)
            <p style="font-size:12px;margin-bottom:8px;">
                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($this->sikartap->mcuNurseAssessment->file_ekg) }}" target="_blank">📥 Lihat file EKG saat ini</a>
                &nbsp;
                <button type="button" wire:click="removeEkgFile" wire:confirm="Hapus file EKG?" class="btn-ghost" style="font-size:12px;">🗑️ Hapus</button>
            </p>
        @endif

        <label class="upload-zone" style="display:block;margin-bottom:12px;">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:32px;color:var(--text-faint);margin-bottom:10px;display:block;"></i>
            <p style="font-size:13.5px;font-weight:600;color:var(--text-body);margin:0 0 4px;">
                {{ $fileEkg ? $fileEkg->getClientOriginalName() : 'Klik untuk upload file EKG' }}
            </p>
            <p style="font-size:12px;color:var(--text-muted);margin:0;">PDF, JPG, PNG &middot; Maks. 10MB</p>
            <input type="file" style="display:none;" accept=".pdf,.jpg,.jpeg,.png" wire:model="fileEkg">
        </label>
        @error('fileEkg') <p style="color:var(--danger-text);font-size:12px;">{{ $message }}</p> @enderror

        <h3 style="font-size:14px;font-weight:700;margin-bottom:8px;">Catatan Tambahan</h3>
        <textarea wire:model="form.catatan" class="form-input" rows="3" style="margin-bottom:20px;"></textarea>

        <button type="button" wire:click="save" class="btn-primary">💾 Simpan Data</button>
    </div>
</div>
