<div>
    <div class="page-hero violet" style="margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Beranda</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Sikartap</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">📊 Dashboard Sikartap</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Ringkasan pengajuan karyawan tetap</p>
        </div>
    </div>

    @if ($this->canFilterByCompany)
        <div class="mb-4" style="max-width:280px;">
            <label class="form-label">Perusahaan</label>
            <select wire:model.live="companyId" class="form-input">
                <option value="">Semua perusahaan</option>
                @foreach ($this->companyOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- Widget Buka/Tutup Pengajuan Karyawan Tetap - cuma muncul kalau 1 company spesifik
    terpilih, user punya izin BUKA_TUTUP_PENGAJUAN_KARTAP, DAN mode company ini "Buka/Tutup
    Manual". Mode "Berdasarkan Masa Kerja" tidak butuh (dan tidak menampilkan) toggle ini sama
    sekali - tombol pengajuan otomatis muncul begitu masa kerja karyawan cukup, tidak ada
    "buka/tutup" buat dikelola (instruksi user 2026-09-27). Mode diatur di Pengaturan Kepegawaian. --}}
    @if ($this->showBukaTutupToggle)
        @php $dibuka = $this->pengangkatanDibuka; @endphp
        <div style="background:var(--surface);border-radius:14px;box-shadow:var(--shadow-card);padding:16px 18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div>
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Pengajuan Karyawan Tetap</p>
                <p style="font-size:11.5px;color:var(--text-muted);margin:0;">Tombol pengajuan di tab Karir karyawan ikut status di bawah.</p>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                <span class="badge {{ $dibuka ? 'badge-approved' : 'badge-cancelled' }}">{{ $dibuka ? 'Sedang Dibuka' : 'Sedang Ditutup' }}</span>
                <button type="button" wire:click="toggleKartapDibuka" wire:confirm="{{ $dibuka ? 'Tutup pengajuan Karyawan Tetap untuk perusahaan ini?' : 'Buka pengajuan Karyawan Tetap untuk perusahaan ini?' }}" class="btn-primary" style="font-size:12.5px;">
                    <i class="fa-solid fa-power-off"></i> {{ $dibuka ? 'Tutup' : 'Buka' }}
                </button>
            </div>
        </div>
    @endif

    {{-- Ringkasan padat: 1 strip, bukan 2 blok kartu tinggi. Baris atas = hasil akhir
         (total + pending/disetujui/ditolak). Baris bawah = pipeline antrian per tahap, dibaca
         kiri→kanan sesuai urutan proses Sikartap — kelihatan di tahap mana antrian menumpuk. --}}
    @if ($this->canStatistik || $this->canAntrianTahap)
        <style>
            .sk-rail { background:var(--surface); border-radius:12px; box-shadow:var(--shadow-card); padding:12px 14px; margin-bottom:16px; }
            .sk-rail-top { display:flex; align-items:baseline; flex-wrap:wrap; gap:6px 16px; }
            .sk-rail-total { font-size:22px; font-weight:800; color:var(--text-heading); line-height:1; }
            .sk-rail-total span { font-size:11px; font-weight:600; color:var(--text-faint); text-transform:uppercase; letter-spacing:.05em; margin-left:5px; }
            .sk-rail-pills { display:flex; gap:6px; flex-wrap:wrap; }
            .sk-pill { font-size:11.5px; font-weight:600; padding:3px 9px; border-radius:99px; background:var(--surface-alt,rgba(0,0,0,.04)); }
            .sk-pipe { display:flex; margin-top:12px; overflow-x:auto; scrollbar-width:thin; border-top:1px solid var(--border-soft); padding-top:10px; }
            .sk-stage { flex:1 0 auto; min-width:88px; text-align:center; padding:0 10px; position:relative; }
            .sk-stage + .sk-stage::before { content:''; position:absolute; left:0; top:8px; width:1px; height:26px; background:var(--border-soft); }
            .sk-stage-n { font-size:17px; font-weight:800; color:var(--text-heading); line-height:1; }
            .sk-stage-l { font-size:10.5px; color:var(--text-muted); margin-top:4px; display:block; white-space:nowrap; }
            @media (max-width:520px) { .sk-rail-total { font-size:19px; } .sk-stage { min-width:76px; } }
        </style>
        <div class="sk-rail">
            @if ($this->canStatistik)
                @php $statistik = $this->statistik; @endphp
                <div class="sk-rail-top">
                    <span class="sk-rail-total">{{ $statistik['total'] }}<span>pengajuan</span></span>
                    <div class="sk-rail-pills">
                        <span class="sk-pill" style="color:#d97706;">⏳ {{ $statistik['pending'] }} pending</span>
                        <span class="sk-pill" style="color:#15803d;">✅ {{ $statistik['approved'] }} disetujui</span>
                        <span class="sk-pill" style="color:#dc2626;">❌ {{ $statistik['rejected'] }} ditolak</span>
                    </div>
                </div>
            @endif

            @if ($this->canAntrianTahap)
                @php $antrian = $this->antrianTahap; @endphp
                <div class="sk-pipe">
                    <div class="sk-stage"><span class="sk-stage-n">{{ $antrian['tes'] }}</span><span class="sk-stage-l">📝 Tes</span></div>
                    <div class="sk-stage"><span class="sk-stage-n">{{ $antrian['wawancara'] }}</span><span class="sk-stage-l">🎤 Wawancara</span></div>
                    <div class="sk-stage"><span class="sk-stage-n">{{ $antrian['ide_inovasi'] }}</span><span class="sk-stage-l">💡 Ide Inovasi</span></div>
                    <div class="sk-stage"><span class="sk-stage-n">{{ $antrian['mcu'] }}</span><span class="sk-stage-l">🏥 MCU</span></div>
                    <div class="sk-stage"><span class="sk-stage-n">{{ $antrian['keputusan_direktur'] }}</span><span class="sk-stage-l">🏛️ Direktur</span></div>
                </div>
            @endif
        </div>
    @endif

    @if (! $this->canStatistik && ! $this->canAntrianTahap && ! $this->canTabelPengajuan)
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);">Tidak ada widget yang bisa ditampilkan.</p>
        </div>
    @endif

    @if ($this->canTabelPengajuan)
        <div id="tabel-pengajuan" style="margin-top:20px;">
            @if ($this->canViewSikartap)
                <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                    <div style="min-width:220px;flex:1;">
                        <label class="form-label">Cari</label>
                        <div style="position:relative;">
                            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama, NIP..." style="padding-left:38px;">
                        </div>
                    </div>
                    <button type="button" wire:click="export" wire:loading.attr="disabled" wire:target="export" class="btn-ghost" style="padding:9px 16px;font-size:12.5px;">
                        <i class="fa-solid fa-file-excel"></i> Export Excel
                    </button>
                </div>

                @php $periodeCounts = $this->periodeCounts; @endphp
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                    <button type="button" wire:click="selectPeriode('berjalan')" class="doc-type-btn doc-type-btn-sky {{ $periode === 'berjalan' ? 'is-active' : '' }}">
                        <i class="fa-solid fa-calendar-check"></i> Periode Berjalan
                        <span class="doc-type-count">{{ $periodeCounts['berjalan'] }}</span>
                    </button>
                    <button type="button" wire:click="selectPeriode('lama')" class="doc-type-btn doc-type-btn-sky {{ $periode === 'lama' ? 'is-active' : '' }}">
                        <i class="fa-solid fa-clock-rotate-left"></i> Lebih dari {{ \App\Services\Sikartap\SikartapPeriodeHelper::BATAS_PERIODE_BULAN }} Bulan
                        <span class="doc-type-count">{{ $periodeCounts['lama'] }}</span>
                    </button>
                    <button type="button" wire:click="selectPeriode('semua')" class="doc-type-btn doc-type-btn-navy {{ $periode === 'semua' ? 'is-active' : '' }}">
                        <i class="fa-solid fa-layer-group"></i> Semua
                        <span class="doc-type-count">{{ $periodeCounts['semua'] }}</span>
                    </button>
                </div>

                @if ($periode === 'lama' && count($this->batches) > 0)
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;padding:12px 14px;border:1.5px dashed var(--border);border-radius:10px;">
                        <button type="button" wire:click="selectBatch(null)" class="doc-type-btn {{ $batch === null ? 'is-active' : '' }}" style="padding:6px 12px;font-size:11.5px;">
                            Semua Batch
                        </button>
                        @foreach ($this->batches as $b)
                            <button type="button" wire:click="selectBatch({{ $b['n'] }})" class="doc-type-btn {{ $batch === $b['n'] ? 'is-active' : '' }}" style="padding:6px 12px;font-size:11.5px;" title="{{ $b['label'] }}">
                                Batch {{ $b['n'] }}
                                <span class="doc-type-count">{{ $b['count'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif

            @php
                $pengajuan = $this->pengajuan;
                $canFilterByCompany = $this->canFilterByCompany;
                $canViewSikartap = $this->canViewSikartap;
                $canInputMcu = $this->canInputMcu;
                $statusBadge = fn (string $status) => match ($status) {
                    'approved', 'fit' => ['badge-approved', '✅', 'Disetujui'],
                    'lulus', 'disarankan' => ['badge-approved', '✅', 'Selesai'],
                    'rejected', 'tidak_lulus', 'tidak_disarankan', 'unfit' => ['badge-rejected', '❌', 'Ditolak'],
                    default => ['badge-pending', '⏳', 'Menunggu'],
                };
            @endphp

            {{-- MOBILE: card manual --}}
            <div class="md:hidden">
                @if ($pengajuan->isEmpty())
                    <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                        <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pengajuan.</p>
                    </div>
                @else
                    <div class="pwa-list">
                        @foreach ($pengajuan as $item)
                            @php
                                [$berkasColor, $berkasEmoji, $berkasLabel] = $statusBadge($item->status_berkas);
                                $mcuActions = $this->mcuActionsFor($item);
                            @endphp
                            <div class="pwa-list-row" style="align-items:center;">
                                <div style="flex:1;min-width:0;">
                                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $item->user->name }}</p>
                                    <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                        {{ $item->user->nip ?: 'NIP belum diisi' }}
                                        @if ($canFilterByCompany && $item->user->company?->name)
                                            &middot; {{ $item->user->company->name }}
                                        @endif
                                    </p>
                                    @if ($canViewSikartap)
                                        <span class="badge {{ $berkasColor }}" style="margin-top:4px;display:inline-block;">Berkas: {{ $berkasEmoji }} {{ $berkasLabel }}</span>
                                    @endif
                                </div>

                                <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
                                    @foreach ($mcuActions as $action)
                                        <a href="{{ $action['url'] }}" wire:navigate class="btn-ghost" style="padding:6px 10px;" title="{{ $action['title'] }}">
                                            <i class="fa-solid {{ $action['icon'] }}"></i>
                                        </a>
                                    @endforeach
                                    @if ($canViewSikartap)
                                        <a href="{{ route('sikartap.pengajuan.show', $item) }}" wire:navigate class="btn-ghost" style="padding:6px 10px;" title="Lihat">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div style="margin-top:16px;">
                        {{ $pengajuan->links('livewire.partials.pagination') }}
                    </div>
                @endif
            </div>

            {{-- DESKTOP: tabel custom --}}
            <div class="hidden md:block">
                <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $pengajuan->isEmpty() ? 'display:none;' : '' }}">
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                        <thead>
                            <tr style="border-bottom:1.5px solid var(--border);">
                                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama</th>
                                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">NIP</th>
                                @if ($canViewSikartap)
                                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Berkas</th>
                                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Tes</th>
                                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Wawancara</th>
                                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Ide Inovasi</th>
                                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">MCU</th>
                                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Keputusan Direktur</th>
                                @endif
                                <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pengajuan as $item)
                                @php
                                    $mcuActions = $this->mcuActionsFor($item);
                                    if ($canViewSikartap) {
                                        [$berkasColor, $berkasEmoji, $berkasLabel] = $statusBadge($item->status_berkas);
                                        [$tesColor, $tesEmoji, $tesLabel] = $statusBadge($item->status_tes);
                                        [$wawancaraColor, $wawancaraEmoji, $wawancaraLabel] = $statusBadge($item->status_wawancara);
                                        [$ideColor, $ideEmoji, $ideLabel] = $item->requiresIdeInovasi() ? $statusBadge($item->ideInovasi?->status_atasan ?? 'pending') : ['badge-cancelled', '➖', 'Dilewati'];
                                        [$mcuColor, $mcuEmoji, $mcuLabel] = $item->requiresMcu() ? $statusBadge($item->status_mcu) : ['badge-cancelled', '➖', 'Dilewati'];
                                        [$direkturColor, $direkturEmoji, $direkturLabel] = $statusBadge($item->status_keputusan_direktur);
                                    }
                                @endphp
                                <tr wire:key="'pengajuan-'.{{ $item->id }}" style="border-bottom:1px solid var(--border-soft);">
                                    <td style="padding:9px 14px;white-space:nowrap;">
                                        <span style="font-weight:600;">{{ $item->user->name }}</span>
                                    </td>
                                    <td style="padding:9px 14px;white-space:nowrap;">{{ $item->user->nip ?: '-' }}</td>
                                    @if ($canViewSikartap)
                                        <td style="padding:9px 14px;text-align:center;font-size:15px;" title="{{ $berkasLabel }}">{{ $berkasEmoji }}</td>
                                        <td style="padding:9px 14px;text-align:center;font-size:15px;" title="{{ $tesLabel }}">{{ $tesEmoji }}</td>
                                        <td style="padding:9px 14px;text-align:center;font-size:15px;" title="{{ $wawancaraLabel }}">{{ $wawancaraEmoji }}</td>
                                        <td style="padding:9px 14px;text-align:center;font-size:15px;" title="{{ $ideLabel }}">{{ $ideEmoji }}</td>
                                        <td style="padding:9px 14px;text-align:center;font-size:15px;" title="{{ $mcuLabel }}">{{ $mcuEmoji }}</td>
                                        <td style="padding:9px 14px;text-align:center;font-size:15px;" title="{{ $direkturLabel }}">{{ $direkturEmoji }}</td>
                                    @endif
                                    <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                        <div style="display:inline-flex;align-items:center;gap:6px;">
                                            @foreach ($mcuActions as $action)
                                                <a href="{{ $action['url'] }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="{{ $action['title'] }}">
                                                    <i class="fa-solid {{ $action['icon'] }}"></i>
                                                </a>
                                            @endforeach
                                            @if ($canViewSikartap)
                                                <a href="{{ route('sikartap.pengajuan.show', $item) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($pengajuan->isEmpty())
                    <div style="padding:32px 16px;text-align:center;">
                        <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada pengajuan.</p>
                    </div>
                @endif

                <div style="margin-top:16px;">
                    {{ $pengajuan->links('livewire.partials.pagination') }}
                </div>
            </div>
        </div>
    @endif
</div>
