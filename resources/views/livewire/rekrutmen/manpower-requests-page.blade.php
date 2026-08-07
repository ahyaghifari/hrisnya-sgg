<div>
<x-page-header title="Permintaan Tenaga Kerja" subtitle="Penambahan kuota, karyawan, dan penggantian karyawan" />

@if ($this->canCreate)
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
        <a href="{{ route('kepegawaian.permintaan.create') }}" wire:navigate class="btn-primary">
            <i class="fa-solid fa-plus"></i> Ajukan Permintaan
        </a>
    </div>
@endif

<div>
    <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
            <div style="min-width:200px;">
                <label class="form-label">Tipe</label>
                <select wire:model.live="type" class="form-input">
                    <option value="">Semua Tipe</option>
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

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

        @php $requests = $this->requests; @endphp

        <!-- MOBILE: card manual -->
        <div class="md:hidden">
            @if ($requests->isEmpty())
                <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada permintaan.</p>
                </div>
            @else
                <div class="pwa-list">
                    @foreach ($requests as $req)
                        @php
                            $status = \App\Enum\JobPositionReqStatus::tryFrom($req->status);
                            $badge = match ($status) {
                                \App\Enum\JobPositionReqStatus::APPROVED => 'badge-approved',
                                \App\Enum\JobPositionReqStatus::REJECTED => 'badge-rejected',
                                default => 'badge-pending',
                            };
                            $type = \App\Enum\ManpowerRequestType::from($req->type);
                            $typeBadge = $type === \App\Enum\ManpowerRequestType::ADDITIONAL_QUOTA ? 'badge-info' : 'badge-cancelled';
                        @endphp
                        <a href="{{ route('kepegawaian.permintaan.show', $req) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                            <div class="pwa-tile-icon neutral"><i class="fa-solid fa-right-left"></i></div>
                            <div style="flex:1;min-width:0;">
                                <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $req->jobPosition?->name ?? '-' }}</p>
                                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                    <span class="badge {{ $typeBadge }}">{{ $type->getLabel() }}</span>
                                    Jumlah {{ $req->quantity }}
                                </p>
                            </div>
                            <span class="badge {{ $badge }}">{{ $status?->label() ?? $req->status }}</span>
                        </a>
                    @endforeach
                </div>

                <div style="margin-top:16px;">
                    {{ $requests->links('livewire.partials.pagination') }}
                </div>
            @endif
        </div>

        <!-- DESKTOP: tabel -->
        <div class="hidden md:block">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $requests->isEmpty() ? 'display:none;' : '' }}">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Posisi</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Unit</th>
                            @if ($this->canBrowseAllCompanies)
                                <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                            @endif
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tipe</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jumlah</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Status</th>
                            <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $req)
                            @php
                                $status = \App\Enum\JobPositionReqStatus::tryFrom($req->status);
                                $badge = match ($status) {
                                    \App\Enum\JobPositionReqStatus::APPROVED => 'badge-approved',
                                    \App\Enum\JobPositionReqStatus::REJECTED => 'badge-rejected',
                                    default => 'badge-pending',
                                };
                                $type = \App\Enum\ManpowerRequestType::from($req->type);
                                $typeBadge = $type === \App\Enum\ManpowerRequestType::ADDITIONAL_QUOTA ? 'badge-info' : 'badge-cancelled';
                            @endphp
                            <tr wire:key="'mr-'.{{ $req->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $req->jobPosition?->name ?? '-' }}</td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $req->jobPosition?->orgStructure?->name ?? '-' }}</td>
                                @if ($this->canBrowseAllCompanies)
                                    <td style="padding:9px 14px;white-space:nowrap;">{{ $req->company?->name ?? '-' }}</td>
                                @endif
                                <td style="padding:9px 14px;white-space:nowrap;"><span class="badge {{ $typeBadge }}">{{ $type->getLabel() }}</span></td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $req->quantity }}</td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;"><span class="badge {{ $badge }}">{{ $status?->label() ?? $req->status }}</span></td>
                                <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                    <a href="{{ route('kepegawaian.permintaan.show', $req) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat"><i class="fa-solid fa-arrow-right"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($requests->isEmpty())
                <div style="padding:32px 16px;text-align:center;">
                    <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada permintaan.</p>
                </div>
            @endif

            <div style="margin-top:16px;">
                {{ $requests->links('livewire.partials.pagination') }}
            </div>
        </div>
    </div>
</div>
