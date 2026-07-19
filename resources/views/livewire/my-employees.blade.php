<div>
<x-page-header title="MyStaff" />

<div style="margin-bottom:20px;">
    <livewire:pending-approvals-card />
</div>

@if ($canBrowseAllCompanies)
    <div class="mb-4 max-w-sm">
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Pilih Perusahaan</label>
        <select wire:model.live="companyId" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
            <option value="">Pilih perusahaan...</option>
            @foreach ($this->companyOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>
@endif

@php $employees = $this->employees; @endphp

<!-- MOBILE: card manual -->
<div class="md:hidden">
    @if ($employees->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                {{ $canBrowseAllCompanies && ! $companyId ? 'Pilih perusahaan dulu.' : 'Belum ada karyawan di bawah kamu.' }}
            </p>
        </div>
    @else
        <div class="pwa-list">
            @foreach ($employees as $employee)
                @php
                    $initials = collect(preg_split('/\s+/', trim($employee->name)))
                        ->map(fn ($w) => mb_substr($w, 0, 1))
                        ->take(2)
                        ->implode('');

                    $photoPath = $employee->profile?->profile_picture[0] ?? null;
                    $photoUrl = $photoPath && is_string($photoPath) ? \Illuminate\Support\Facades\Storage::url($photoPath) : null;
                @endphp
                <a href="{{ route('my-staff.show', ['record' => $employee]) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="{{ $employee->name }}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                    @else
                        <div class="pwa-tile-icon neutral" style="border-radius:50%;font-size:12px;font-weight:700;">{{ $initials ?: '?' }}</div>
                    @endif
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $employee->name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                            {{ $employee->nip ?: 'NIP belum diisi' }}
                            @if ($employee->profile?->jobPosition?->name)
                                &middot; {{ $employee->profile->jobPosition->name }}
                            @endif
                        </p>
                        @if ($employee->profile?->department?->name || ($canBrowseAllCompanies && $employee->company?->name))
                            <p style="font-size:11px;color:var(--text-faint);margin-top:2px;">
                                {{ $employee->profile?->department?->name }}
                                @if ($canBrowseAllCompanies && $employee->company?->name)
                                    &middot; {{ $employee->company->name }}
                                @endif
                            </p>
                        @endif
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @endforeach
        </div>

        <div style="margin-top:16px;">
            {{ $employees->links('livewire.partials.pagination') }}
        </div>
    @endif
</div>

<!-- DESKTOP: tabel custom, Livewire server-side (sama sumber data dengan card mobile) -->
<div class="hidden md:block">
    <div>
        <div style="margin-bottom:14px;max-width:320px;position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama, NIP, jabatan..." style="padding-left:38px;">
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $employees->isEmpty() ? 'display:none;' : '' }}">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="width:44px;padding:10px 14px;"></th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">NIP</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Departemen</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jabatan / Role</th>
                        @if ($canBrowseAllCompanies)
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                        @endif
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employees as $employee)
                        @php
                            $photoPath = $employee->profile?->profile_picture[0] ?? null;
                            $photoUrl = $photoPath && is_string($photoPath) ? \Illuminate\Support\Facades\Storage::url($photoPath) : null;
                            $roleNames = $employee->getRoleNames()->join(', ');
                        @endphp
                        <tr wire:key="'staff-'.{{ $employee->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;">
                                @if ($photoUrl)
                                    <img src="{{ $photoUrl }}" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                @else
                                    <div style="width:32px;height:32px;border-radius:50%;background:var(--surface-alt);"></div>
                                @endif
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                <span style="font-weight:600;">{{ $employee->name }}</span>
                                <span style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $employee->email }}</span>
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;">{{ $employee->nip ?: '-' }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $employee->profile?->department?->name ?? '-' }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                {{ $employee->profile?->jobPosition?->name ?? '-' }}
                                @if ($roleNames)
                                    <span style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $roleNames }}</span>
                                @endif
                            </td>
                            @if ($canBrowseAllCompanies)
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $employee->company?->name ?? '-' }}</td>
                            @endif
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <a href="{{ route('my-staff.show', ['record' => $employee]) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat">
                                    <i class="fa-solid fa-chart-bar"></i>
                                </a>
                                <a href="{{ route('my-staff.edit', ['record' => $employee]) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($employees->isEmpty())
            <div style="padding:32px 16px;text-align:center;">
                <p style="font-size:13px;color:var(--text-muted);margin:0;">
                    {{ $canBrowseAllCompanies && ! $companyId ? 'Pilih perusahaan dulu.' : 'Belum ada karyawan di bawah kamu.' }}
                </p>
            </div>
        @endif

        <div style="margin-top:16px;">
            {{ $employees->links('livewire.partials.pagination') }}
        </div>
    </div>
</div>

</div>
