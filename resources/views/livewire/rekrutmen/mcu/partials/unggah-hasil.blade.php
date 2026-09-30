{{--
    Kartu unggah berkas hasil MCU rekrutmen, dipakai keempat stasiun. Sejak 2026-09-29 stasiun MCU
    rekrutmen cuma mengunggah berkas hasilnya - rincian per pemeriksaan tidak diisi lagi.

    Variabel: $judul (nama stasiun), dan opsional $slot lewat @slot tidak dipakai di sini - halaman
    dokter menambahkan pilihan rekomendasi fit-nya sendiri sebelum meng-include ini.
--}}
<div>
    <label class="form-label">Berkas Hasil {{ $judul }} <span style="color:var(--danger-text);">*</span></label>
    <p style="font-size:11px;color:var(--text-muted);margin:-2px 0 6px;">PDF atau gambar, maksimal 10 MB.</p>
    <input type="file" wire:model="file" class="form-input" accept=".pdf,.jpg,.jpeg,.png">
    @error('file') <p style="color:var(--danger-text);font-size:12px;margin-top:4px;">{{ $message }}</p> @enderror

    <div wire:loading wire:target="file" style="font-size:12px;color:var(--text-muted);margin-top:6px;">
        Mengunggah berkas…
    </div>

    @if($existingFilePath)
        <div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="font-size:12px;color:var(--text-muted);">Berkas tersimpan:</span>
            <button type="button" wire:click="downloadFile"
                style="font-size:12px;font-weight:600;color:var(--primary-text);text-decoration:underline;background:none;border:0;padding:0;cursor:pointer;">
                Unduh berkas
            </button>
            <span style="font-size:11px;color:var(--text-muted);">— unggah berkas baru untuk menggantinya.</span>
        </div>
    @endif
</div>
