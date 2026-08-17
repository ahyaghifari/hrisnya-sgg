<div>
<x-page-header title="Alur Persetujuan" subtitle="Atur alur persetujuan berjenjang untuk berbagai modul" />

<div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;margin-bottom:14px;">
    @if ($this->canFilterByCompany)
        <select wire:model.live="companyFilter" class="form-input" style="width:auto;padding:6px 10px;font-size:12.5px;">
            <option value="">Semua Perusahaan</option>
            @foreach ($this->companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
    @else
        <span></span>
    @endif

    @can(\App\Enum\CustomPermissionEnum::KELOLA_ALUR_PERSETUJUAN->value)
        <a href="{{ route('kepegawaian.alur-persetujuan.tambah') }}" wire:navigate class="btn-primary">
            <i class="fa-solid fa-plus"></i> Tambah Alur
        </a>
    @endcan
</div>

@php $flows = $this->flows; @endphp

<!-- MOBILE: card -->
<div class="md:hidden">
    @if ($flows->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada alur persetujuan.</p>
        </div>
    @else
        <div class="pwa-list">
            @foreach ($flows as $flow)
                <a href="{{ route('kepegawaian.alur-persetujuan.edit', $flow) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-bars-staggered"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $flow->name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                            {{ $this->moduleLabel($flow->module) }}
                            @if ($this->canFilterByCompany) &middot; {{ $flow->company?->name }} @endif
                            &middot; {{ $flow->steps->count() }} step
                        </p>
                        <span class="badge {{ $flow->flow_type === 'default' ? 'badge-approved' : 'badge-pending' }}" style="margin-top:4px;">{{ $flow->flow_type === 'default' ? 'Default' : 'Spesifik' }}</span>
                        <span class="badge {{ $flow->is_active ? 'badge-approved' : 'badge-cancelled' }}" style="margin-top:4px;">{{ $flow->is_active ? 'Aktif' : 'Tidak Aktif' }}</span>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @endforeach
        </div>

        <div style="margin-top:16px;">
            {{ $flows->links('livewire.partials.pagination') }}
        </div>
    @endif
</div>

<!-- DESKTOP: tabel -->
<div class="hidden md:block">
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $flows->isEmpty() ? 'display:none;' : '' }}">
        <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
            <thead>
                <tr style="border-bottom:1.5px solid var(--border);">
                    <th style="text-align:left;padding:10px 14px;">Nama</th>
                    <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Modul</th>
                    @if ($this->canFilterByCompany)
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                    @endif
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Tipe</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Step</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                    <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($flows as $flow)
                    <tr wire:key="'flow-'.{{ $flow->id }}" style="border-bottom:1px solid var(--border-soft);">
                        <td style="padding:9px 14px;font-weight:600;color:var(--text-heading);">{{ $flow->name }}</td>
                        <td style="padding:9px 14px;white-space:nowrap;">{{ $this->moduleLabel($flow->module) }}</td>
                        @if ($this->canFilterByCompany)
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $flow->company?->name }}</td>
                        @endif
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                            <span class="badge {{ $flow->flow_type === 'default' ? 'badge-approved' : 'badge-pending' }}">{{ $flow->flow_type === 'default' ? 'Default' : 'Spesifik' }}</span>
                        </td>
                        <td style="padding:9px 14px;text-align:center;">{{ $flow->steps->count() }}</td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                            <span class="badge {{ $flow->is_active ? 'badge-approved' : 'badge-cancelled' }}">{{ $flow->is_active ? 'Aktif' : 'Tidak Aktif' }}</span>
                        </td>
                        <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                            <a href="{{ route('kepegawaian.alur-persetujuan.edit', $flow) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Edit"><i class="fa-solid fa-pen"></i></a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($flows->isEmpty())
        <div style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada alur persetujuan.</p>
        </div>
    @endif

    <div style="margin-top:16px;">
        {{ $flows->links('livewire.partials.pagination') }}
    </div>
</div>

</div>
