<div>
    <div class="page-hero violet" style="margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Beranda</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Sikartap</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Jadwal Tes &amp; Wawancara</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Semua jadwal yang sudah ditentukan, lintas pengajuan</p>
        </div>
    </div>

    <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
        @if ($canFilterByCompany)
            <div style="min-width:220px;">
                <label class="form-label">Perusahaan</label>
                <select wire:model.live="companyId" class="form-input">
                    <option value="">Semua perusahaan</option>
                    @foreach ($this->companyOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div style="min-width:220px;flex:1;">
            <label class="form-label">Cari</label>
            <div style="position:relative;">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama, NIP..." style="padding-left:38px;">
            </div>
        </div>
    </div>

    @php
        $jadwal = $this->jadwal;
        $statusBadge = fn (?string $status) => match ($status) {
            'approved', 'fit' => ['badge-approved', '✅', 'Disetujui'],
            'lulus', 'disarankan' => ['badge-approved', '✅', 'Selesai'],
            'rejected', 'tidak_lulus', 'tidak_disarankan', 'unfit' => ['badge-rejected', '❌', 'Ditolak'],
            default => ['badge-pending', '⏳', 'Menunggu'],
        };
    @endphp

    @if ($jadwal->isEmpty())
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                {{ $canFilterByCompany && ! $companyId ? 'Pilih perusahaan dulu.' : 'Belum ada jadwal tes/wawancara yang ditentukan.' }}
            </p>
        </div>
    @else
        {{-- MOBILE: card manual --}}
        <div class="md:hidden">
            <div class="pwa-list">
                @foreach ($jadwal as $item)
                    <a href="{{ route('sikartap.pengajuan.show', $item) }}" wire:navigate class="pwa-list-row" style="align-items:flex-start;text-decoration:none;">
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $item->user->name }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $item->user->nip }}</p>

                            @if ($item->testSchedule)
                                <p style="font-size:12px;color:var(--text-body);margin-top:6px;">
                                    <i class="fa-solid fa-pen-ruler" style="color:var(--text-faint);"></i> Tes: {{ \Carbon\Carbon::parse($item->testSchedule->tanggal_tes)->translatedFormat('j M Y') }}, {{ $item->testSchedule->waktu_tes }} &middot; {{ $item->testSchedule->tempat_tes }}
                                </p>
                            @endif
                            @if ($item->interviewSchedule)
                                <p style="font-size:12px;color:var(--text-body);margin-top:4px;">
                                    <i class="fa-solid fa-comments" style="color:var(--text-faint);"></i> Wawancara: {{ \Carbon\Carbon::parse($item->interviewSchedule->tanggal_wawancara)->translatedFormat('j M Y') }}, {{ $item->interviewSchedule->waktu_wawancara }} &middot; {{ $item->interviewSchedule->tempat_wawancara }}
                                </p>
                            @endif
                        </div>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                @endforeach
            </div>

            <div style="margin-top:16px;">
                {{ $jadwal->links('livewire.partials.pagination') }}
            </div>
        </div>

        {{-- DESKTOP: tabel custom --}}
        <div class="hidden md:block">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Karyawan</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Jadwal Tes</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Hasil Tes</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Jadwal Wawancara</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Hasil Wawancara</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($jadwal as $item)
                            @php
                                [$tesColor, $tesEmoji, $tesLabel] = $statusBadge($item->status_tes);
                                [$wawancaraColor, $wawancaraEmoji, $wawancaraLabel] = $statusBadge($item->status_wawancara);
                            @endphp
                            <tr wire:key="'jadwal-'.{{ $item->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;white-space:nowrap;">
                                    <span style="font-weight:600;">{{ $item->user->name }}</span>
                                    <span style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $item->user->nip }}</span>
                                </td>
                                <td style="padding:9px 14px;">
                                    @if ($item->testSchedule)
                                        {{ \Carbon\Carbon::parse($item->testSchedule->tanggal_tes)->translatedFormat('j M Y') }}, {{ $item->testSchedule->waktu_tes }}
                                        <span style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $item->testSchedule->tempat_tes }}</span>
                                    @else
                                        <span style="color:var(--text-faint);font-style:italic;">-</span>
                                    @endif
                                </td>
                                <td style="padding:9px 14px;text-align:center;">
                                    @if ($item->testSchedule)
                                        <span style="font-size:15px;" title="{{ $tesLabel }}">{{ $tesEmoji }}</span>
                                    @else
                                        <span style="color:var(--text-faint);">-</span>
                                    @endif
                                </td>
                                <td style="padding:9px 14px;">
                                    @if ($item->interviewSchedule)
                                        {{ \Carbon\Carbon::parse($item->interviewSchedule->tanggal_wawancara)->translatedFormat('j M Y') }}, {{ $item->interviewSchedule->waktu_wawancara }}
                                        <span style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $item->interviewSchedule->tempat_wawancara }}</span>
                                    @else
                                        <span style="color:var(--text-faint);font-style:italic;">-</span>
                                    @endif
                                </td>
                                <td style="padding:9px 14px;text-align:center;">
                                    @if ($item->interviewSchedule)
                                        <span style="font-size:15px;" title="{{ $wawancaraLabel }}">{{ $wawancaraEmoji }}</span>
                                    @else
                                        <span style="color:var(--text-faint);">-</span>
                                    @endif
                                </td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <a href="{{ route('sikartap.pengajuan.show', $item) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Kelola">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                {{ $jadwal->links('livewire.partials.pagination') }}
            </div>
        </div>
    @endif
</div>
