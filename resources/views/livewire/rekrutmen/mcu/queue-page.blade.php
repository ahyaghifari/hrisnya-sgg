<div>
    <div class="page-hero" style="background:linear-gradient(130deg,#6e561f 0%,#98782d 100%);margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Antrian MCU Rekrutmen</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Pelamar yang sudah diundang Medical Check Up</p>
        </div>
    </div>

    <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
        <div style="min-width:260px;">
            <label class="form-label">Perusahaan</label>
            <select wire:model.live="companyId" class="form-input">
                <option value="">-- Pilih perusahaan --</option>
                @foreach ($this->companyOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @php $antrian = $this->antrian; @endphp

    @if ($antrian->isEmpty())
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                {{ ! $companyId ? 'Pilih perusahaan dulu.' : 'Belum ada pelamar yang diundang MCU di perusahaan ini.' }}
            </p>
        </div>
    @else
        {{-- MOBILE --}}
        <div class="md:hidden">
            <div class="pwa-list">
                @foreach ($antrian as $app)
                    <div class="pwa-list-row" style="align-items:flex-start;">
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:15px;font-weight:600;color:var(--text-heading);">{{ $app->answersMap()['full_name'] ?? '—' }}</p>
                            <p style="font-size:12.5px;color:var(--text-muted);margin:2px 0 0;">{{ $app->jobOpening?->title ?? '—' }}</p>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                                @foreach ($this->stationsFor($app) as $st)
                                    @include('livewire.rekrutmen.mcu.partials.tombol-stasiun', ['st' => $st])
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- DESKTOP --}}
        <div class="hidden md:block">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:14.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:13px 16px;white-space:nowrap;">Pelamar</th>
                            <th style="text-align:left;padding:13px 16px;white-space:nowrap;">Lowongan</th>
                            <th style="text-align:left;padding:13px 16px;white-space:nowrap;">Isi MCU</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($antrian as $app)
                            <tr wire:key="mcu-{{ $app->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:12px 16px;white-space:nowrap;font-weight:600;">{{ $app->answersMap()['full_name'] ?? '—' }}</td>
                                <td style="padding:12px 16px;color:var(--text-muted);">{{ $app->jobOpening?->title ?? '—' }}</td>
                                <td style="padding:12px 16px;">
                                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                        @foreach ($this->stationsFor($app) as $st)
                                            @include('livewire.rekrutmen.mcu.partials.tombol-stasiun', ['st' => $st])
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Modal isi stasiun: perawat, lab, radiologi. Isiannya cuma unggah berkas, jadi tidak perlu
         pindah halaman. Dokter tidak lewat sini - dia punya halaman sendiri.

         Transisinya menyalin pola modal Tugas: modal dirender kondisional, jadi elemennya baru masuk
         DOM tiap dibuka dan animasi CSS jalan sendiri tanpa JS. --}}
    <style>
        @keyframes mcu-modal-fade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes mcu-modal-pop { from { opacity: 0; transform: translateY(14px) scale(0.97); } to { opacity: 1; transform: none; } }
        @keyframes mcu-modal-slide { from { transform: translateY(100%); } to { transform: none; } }
        .mcu-modal { animation: mcu-modal-fade 0.18s ease-out; align-items: center; }
        .mcu-modal > .modal-box { animation: mcu-modal-pop 0.22s cubic-bezier(0.2, 0.8, 0.2, 1); border-radius: 20px; margin: auto; }
        @media (max-width: 767.98px) {
            .mcu-modal > .modal-box { animation: mcu-modal-slide 0.25s cubic-bezier(0.2, 0.8, 0.2, 1); }
        }
        @media (prefers-reduced-motion: reduce) {
            .mcu-modal, .mcu-modal > .modal-box { animation: none; }
        }
    </style>

    @if ($station)
        <div class="modal-overlay mcu-modal" wire:click.self="closeStation" @keydown.escape.window="$wire.closeStation()">
            <div class="modal-box modal-box-lg">
                <div style="display:flex;align-items:start;justify-content:space-between;gap:12px;margin-bottom:16px;">
                    <h2 style="font-size:17px;font-weight:800;margin:0;">Hasil {{ $this->stationLabel }}</h2>
                    <button type="button" wire:click="closeStation"
                        style="background:none;border:0;cursor:pointer;font-size:22px;line-height:1;color:var(--text-muted);">&times;</button>
                </div>

                @include('livewire.rekrutmen.mcu.partials.unggah-hasil', ['judul' => $this->stationLabel])

                <div style="display:flex;gap:10px;margin-top:20px;">
                    <button type="button" wire:click="saveStation" wire:loading.attr="disabled" class="btn-primary">💾 Simpan</button>
                    <button type="button" wire:click="closeStation" class="btn-ghost">Batal</button>
                </div>
            </div>
        </div>
    @endif
</div>
