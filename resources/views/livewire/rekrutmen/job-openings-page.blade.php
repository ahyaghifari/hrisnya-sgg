<div>
<x-page-header title="Lowongan Kerja" subtitle="Lowongan dibuka otomatis dari Permintaan Tenaga Kerja yang disetujui" />

<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
    <button type="button" wire:click="testRekrutmenConnection" wire:loading.attr="disabled" class="btn-ghost" style="padding:5px 10px;font-size:11px;">
        <i class="fa-solid fa-plug-circle-check" wire:loading.remove wire:target="testRekrutmenConnection"></i>
        <i class="fa-solid fa-spinner fa-spin" wire:loading wire:target="testRekrutmenConnection"></i>
        Tes Koneksi Rekrutmen
    </button>
</div>

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

@php
    $jobOpenings = $this->jobOpenings;
    $rekrutmenCounts = $this->rekrutmenApplicantCounts;
    $unlinked = $this->unlinkedRekrutmenOpenings;
@endphp

@if (count($unlinked) > 0)
    <div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid #d97706;border-radius:12px;padding:14px 16px;margin-bottom:16px;">
        <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 3px;">
            <i class="fa-solid fa-link-slash" style="margin-right:6px;color:#d97706;"></i>
            {{ count($unlinked) }} lowongan di sistem Rekrutmen belum terhubung ke HRIS
        </p>
        <p style="font-size:11px;color:var(--text-muted);margin:0 0 10px;">Dibuat manual di Rekrutmen, bukan dari Permintaan Tenaga Kerja HRIS.</p>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr style="border-bottom:1px solid var(--border);">
                        <th style="text-align:left;padding:6px 10px;white-space:nowrap;">Judul</th>
                        <th style="text-align:left;padding:6px 10px;white-space:nowrap;">Perusahaan</th>
                        <th style="text-align:center;padding:6px 10px;white-space:nowrap;">Kuota</th>
                        <th style="text-align:center;padding:6px 10px;white-space:nowrap;">Batas Lamar</th>
                        <th style="text-align:center;padding:6px 10px;white-space:nowrap;">Aktif</th>
                        <th style="text-align:center;padding:6px 10px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($unlinked as $row)
                        <tr style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:6px 10px;font-weight:600;">{{ $row['title'] ?? '-' }}</td>
                            <td style="padding:6px 10px;">{{ $row['company_name'] ?? ($row['hris_company_code'] ?? '-') }}</td>
                            <td style="padding:6px 10px;text-align:center;">{{ $row['quota'] ?? '-' }}</td>
                            <td style="padding:6px 10px;text-align:center;white-space:nowrap;">{{ $row['applications_deadline'] ?? '-' }}</td>
                            <td style="padding:6px 10px;text-align:center;">{{ ($row['is_active'] ?? false) ? 'Ya' : 'Tidak' }}</td>
                            <td style="padding:6px 10px;text-align:center;white-space:nowrap;">
                                @if (! empty($row['id']))
                                    <a href="{{ route('rekrutmen.lowongan.pelamar', $row['id']) }}" wire:navigate class="btn-ghost" style="padding:4px 8px;font-size:10.5px;"><i class="fa-solid fa-users"></i> Pelamar</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<!-- MOBILE: card manual -->
<div class="md:hidden">
    @if ($jobOpenings->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada lowongan kerja.</p>
        </div>
    @else
        <div class="pwa-list">
            @foreach ($jobOpenings as $opening)
                <a href="{{ route('rekrutmen.lowongan.show', $opening) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-briefcase"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $opening->display_name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $opening->jobPosition?->orgStructure?->name ?? '-' }} &middot; Kuota {{ $opening->quota }} &middot; {{ $rekrutmenCounts[$opening->id] ?? '-' }} pelamar</p>
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
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $rekrutmenCounts[$opening->id] ?? '-' }}</td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;"><span class="badge {{ $opening->openingStatus()->getBadgeClass() }}">{{ $opening->openingStatus()->getLabel() }}</span></td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                            <a href="{{ route('rekrutmen.lowongan.pelamar', $opening) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Pelamar"><i class="fa-solid fa-users"></i></a>
                            <a href="{{ route('rekrutmen.lowongan.show', $opening) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat"><i class="fa-solid fa-arrow-right"></i></a>
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
