<div>
<x-page-header title="Lowongan Kerja" subtitle="Lowongan dibuka otomatis dari Permintaan Tenaga Kerja yang disetujui" />

<div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
    @if ($this->canBrowseAllCompanies)
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
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Nama posisi..." style="padding-left:38px;">
        </div>
    </div>
</div>

@php $jobOpenings = $this->jobOpenings; @endphp

<!-- MOBILE: card manual -->
<div class="md:hidden">
    @if ($jobOpenings->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada lowongan kerja.</p>
        </div>
    @else
        <div class="pwa-list">
            @foreach ($jobOpenings as $opening)
                <a href="{{ route('kepegawaian.lowongan.show', $opening) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-briefcase"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $opening->display_name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $opening->jobPosition?->orgStructure?->name ?? '-' }} &middot; Kuota {{ $opening->quota }} &middot; {{ $opening->applicants_count }} pelamar</p>
                    </div>
                    <span class="badge {{ $opening->openingStatus()->getBadgeClass() }}">{{ $opening->openingStatus()->getLabel() }}</span>
                </a>
            @endforeach
        </div>

        <div style="margin-top:16px;">
            {{ $jobOpenings->links('livewire.partials.pagination') }}
        </div>
    @endif
</div>

<!-- DESKTOP: tabel -->
<div class="hidden md:block">
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $jobOpenings->isEmpty() ? 'display:none;' : '' }}">
        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
            <thead>
                <tr style="border-bottom:1.5px solid var(--border);">
                    <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Posisi</th>
                    <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Unit</th>
                    @if ($this->canBrowseAllCompanies)
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                    @endif
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Kuota</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Pelamar</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jobOpenings as $opening)
                    <tr wire:key="'jo-'.{{ $opening->id }}" style="border-bottom:1px solid var(--border-soft);">
                        <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $opening->display_name }}</td>
                        <td style="padding:9px 14px;white-space:nowrap;">{{ $opening->jobPosition?->orgStructure?->name ?? '-' }}</td>
                        @if ($this->canBrowseAllCompanies)
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $opening->company?->name ?? '-' }}</td>
                        @endif
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $opening->quota }}</td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $opening->applicants_count }}</td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;"><span class="badge {{ $opening->openingStatus()->getBadgeClass() }}">{{ $opening->openingStatus()->getLabel() }}</span></td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                            <a href="{{ route('kepegawaian.lowongan.show', $opening) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat"><i class="fa-solid fa-arrow-right"></i></a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($jobOpenings->isEmpty())
        <div style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada lowongan kerja.</p>
        </div>
    @endif

    <div style="margin-top:16px;">
        {{ $jobOpenings->links('livewire.partials.pagination') }}
    </div>
</div>
</div>
