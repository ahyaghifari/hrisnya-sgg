@php $nama = $this->invitee->jobApplication->answersMap()['full_name'] ?? '—'; @endphp
<div class="form-page-wrap">
    <div class="page-hero" style="background:linear-gradient(130deg,#6e561f 0%,#98782d 100%);">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Pemeriksaan Dokter</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">{{ $nama }}</p>
        </div>
    </div>

    {{-- Hasil stasiun lain lebih dulu: dokter perlu membacanya sebelum memutuskan fit/tidak fit. --}}
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:22px;margin-bottom:16px;">
        <h2 style="font-size:16px;font-weight:800;margin:0 0 4px;">Hasil Pemeriksaan Sebelumnya</h2>
        <p style="font-size:12.5px;color:var(--text-muted);margin:0 0 14px;">Berkas dari perawat, laboratorium, dan radiologi untuk pelamar ini.</p>

        @foreach ($this->lampiran as $item)
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 0;border-top:1px solid var(--border-soft);">
                <span style="font-size:14px;font-weight:600;">{{ $item['stasiun'] }}</span>
                @if ($item['path'])
                    <button type="button" wire:click="downloadLampiran('{{ $item['stasiun'] }}')" class="btn-ghost" style="padding:7px 14px;font-size:13px;">
                        <i class="fa-solid fa-download"></i> Unduh
                    </button>
                @else
                    <span style="font-size:12.5px;color:var(--text-muted);">Belum diunggah</span>
                @endif
            </div>
        @endforeach
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:22px;">
        @include('livewire.rekrutmen.mcu.partials.unggah-hasil', ['judul' => 'Pemeriksaan Dokter'])

        <div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--border);">
            <label class="form-label">Rekomendasi Dokter <span style="color:var(--danger-text);">*</span></label>
            {{-- Kartu pilihan, bukan radio polos: keputusannya cuma dua dan berlawanan arah, jadi
                 dibuat besar dan berwarna supaya tidak salah pencet dan terbaca sekilas. --}}
            <style>
                .fit-pilihan { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; }
                .fit-kartu {
                    display:flex; align-items:center; gap:12px; padding:16px; border-radius:14px; cursor:pointer;
                    border:2px solid var(--border); background:var(--surface);
                    transition: border-color .15s ease, background-color .15s ease, transform .12s ease, box-shadow .15s ease;
                }
                .fit-kartu:hover { transform: translateY(-1px); box-shadow: var(--shadow-card); }
                .fit-kartu input { position:absolute; opacity:0; pointer-events:none; }
                .fit-ikon {
                    width:38px; height:38px; border-radius:11px; display:grid; place-items:center;
                    font-size:16px; flex-shrink:0; background:var(--border-soft); color:var(--text-muted);
                    transition: background-color .15s ease, color .15s ease;
                }
                .fit-kartu[data-aktif="1"][data-nilai="fit"] { border-color:var(--brand-success); background:color-mix(in srgb, var(--brand-success) 8%, transparent); }
                .fit-kartu[data-aktif="1"][data-nilai="fit"] .fit-ikon { background:var(--brand-success); color:#fff; }
                .fit-kartu[data-aktif="1"][data-nilai="unfit"] { border-color:var(--danger-text); background:color-mix(in srgb, var(--danger-text) 8%, transparent); }
                .fit-kartu[data-aktif="1"][data-nilai="unfit"] .fit-ikon { background:var(--danger-text); color:#fff; }
                @media (max-width: 520px) { .fit-pilihan { grid-template-columns:1fr; } }
                @media (prefers-reduced-motion: reduce) { .fit-kartu { transition:none; } .fit-kartu:hover { transform:none; } }
            </style>

            <div class="fit-pilihan">
                @foreach([
                    'fit' => ['label' => 'Fit', 'ket' => 'Layak bekerja', 'ikon' => 'fa-circle-check'],
                    'unfit' => ['label' => 'Tidak Fit', 'ket' => 'Tidak layak bekerja', 'ikon' => 'fa-circle-xmark'],
                ] as $nilai => $opsi)
                    <label class="fit-kartu" data-nilai="{{ $nilai }}" data-aktif="{{ $rekomendasiFit === $nilai ? '1' : '0' }}">
                        <input type="radio" wire:model.live="rekomendasiFit" value="{{ $nilai }}">
                        <span class="fit-ikon"><i class="fa-solid {{ $opsi['ikon'] }}"></i></span>
                        <span>
                            <span style="display:block;font-size:15px;font-weight:700;">{{ $opsi['label'] }}</span>
                            <span style="display:block;font-size:12px;color:var(--text-muted);margin-top:1px;">{{ $opsi['ket'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('rekomendasiFit') <p style="color:var(--danger-text);font-size:12px;margin-top:6px;">{{ $message }}</p> @enderror
        </div>

        <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary" style="margin-top:20px;">💾 Simpan</button>
    </div>
</div>
