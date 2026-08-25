<div class="form-page-wrap-wide">
    <div class="page-hero violet">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('sikartap.ide-inovasi.atasan.queue') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Penilaian Ide Inovasi</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Nilai</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">💡 Nilai Ide Inovasi</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">{{ $this->sikartap->user->name }} &middot; {{ $this->sikartap->user->nip ?: '-' }}</p>
        </div>
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;margin-bottom:16px;">
        <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin-bottom:10px;">📎 Berkas Ide Inovasi</p>
        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($this->sikartap->ideInovasi->file_ide_inovasi) }}" target="_blank" class="btn-ghost" style="padding:6px 14px;font-size:12.5px;">
            <i class="fa-solid fa-file"></i> Lihat / Unduh Berkas
        </a>
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:20px;">
        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin-bottom:4px;">Rubrik Penilaian</p>
        <p style="font-size:11.5px;color:var(--text-muted);margin-bottom:16px;">Tiap kriteria dinilai 0-5. Nilai akhir dihitung otomatis (bobot berbeda tiap kriteria) — lulus kalau ≥ 75.</p>

        @php
            $kriteria = [
                'skor_identifikasi_masalah' => ['Identifikasi Masalah', 20],
                'skor_analisis_masalah' => ['Analisis Masalah', 25],
                'skor_kualitas_inovasi' => ['Kualitas Inovasi', 30],
                'skor_potensi_manfaat' => ['Potensi Manfaat', 15],
                'skor_presentasi' => ['Presentasi', 10],
            ];
        @endphp

        <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:18px;">
            @foreach ($kriteria as $key => [$label, $bobot])
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                    <div style="flex:1;min-width:200px;">
                        <label class="form-label" style="margin:0;">{{ $label }}</label>
                        <p style="font-size:11px;color:var(--text-faint);margin:2px 0 0;">Bobot {{ $bobot }}%</p>
                    </div>
                    <select wire:model="skor.{{ $key }}" class="form-input" style="max-width:100px;">
                        @foreach ([0, 1, 2, 3, 4, 5] as $n)
                            <option value="{{ $n }}">{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                @error("skor.{$key}") <p style="color:var(--danger-text);font-size:12px;margin:-8px 0 0;">{{ $message }}</p> @enderror
            @endforeach
        </div>

        <label class="form-label">Catatan (opsional)</label>
        <textarea wire:model="catatan" class="form-input" rows="3" style="margin-bottom:18px;" placeholder="Masukan/alasan tambahan..."></textarea>
        @error('catatan') <p style="color:var(--danger-text);font-size:12px;margin-top:-12px;margin-bottom:12px;">{{ $message }}</p> @enderror

        <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" wire:confirm="Kirim penilaian ini? Nilai akhir & status kelulusan akan dihitung otomatis." class="btn-primary">
            <i class="fa-solid fa-paper-plane"></i> Kirim Penilaian
        </button>
    </div>
</div>
