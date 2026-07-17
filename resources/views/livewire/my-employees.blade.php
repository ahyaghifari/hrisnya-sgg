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

<!-- MOBILE: card manual -->
<div class="md:hidden">
    @php $employees = $this->employees; @endphp

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

<!-- DESKTOP: tabel custom + List.js (cari/sortir/pagination instan, lihat resources/js/app.js) -->
<div class="hidden md:block">
    @php $staffRows = $this->staffTableRows; @endphp

    <div id="my-staff-list" data-list-table data-list-value-names="name,email,nip,jobposition,rolenames,company" data-list-page-size="15">
        <div style="margin-bottom:14px;max-width:320px;position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
            <input type="search" class="form-input search" placeholder="Cari nama, NIP, jabatan..." style="padding-left:38px;">
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;{{ $staffRows->isEmpty() ? 'display:none;' : '' }}" class="table-wrap">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="width:44px;padding:10px 14px;"></th>
                        <th class="sort" data-sort="name" style="text-align:left;padding:10px 14px;white-space:nowrap;cursor:pointer;">Nama <i class="fa-solid fa-sort" style="font-size:9px;color:var(--text-faint);"></i></th>
                        <th class="sort" data-sort="nip" style="text-align:left;padding:10px 14px;white-space:nowrap;cursor:pointer;">NIP <i class="fa-solid fa-sort" style="font-size:9px;color:var(--text-faint);"></i></th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Departemen</th>
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Jabatan / Role</th>
                        @if ($canBrowseAllCompanies)
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                        @endif
                        <th style="text-align:center;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @foreach ($staffRows as $employee)
                        @php
                            $photoPath = $employee->profile?->profile_picture[0] ?? null;
                            $photoUrl = $photoPath && is_string($photoPath) ? \Illuminate\Support\Facades\Storage::url($photoPath) : null;
                            $roleNames = $employee->getRoleNames()->join(', ');
                        @endphp
                        <tr style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;">
                                @if ($photoUrl)
                                    <img src="{{ $photoUrl }}" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                @else
                                    <div style="width:32px;height:32px;border-radius:50%;background:var(--surface-alt);"></div>
                                @endif
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                <span class="name" style="font-weight:600;">{{ $employee->name }}</span>
                                <span class="email" style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $employee->email }}</span>
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;"><span class="nip">{{ $employee->nip ?: '-' }}</span></td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">{{ $employee->profile?->department?->name ?? '-' }}</td>
                            <td style="padding:9px 14px;text-align:center;white-space:nowrap;">
                                <span class="jobposition">{{ $employee->profile?->jobPosition?->name ?? '-' }}</span>
                                @if ($roleNames)
                                    <span class="rolenames" style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $roleNames }}</span>
                                @endif
                            </td>
                            @if ($canBrowseAllCompanies)
                                <td style="padding:9px 14px;white-space:nowrap;"><span class="company">{{ $employee->company?->name ?? '-' }}</span></td>
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

        <div class="list-empty-state" style="padding:32px 16px;text-align:center;{{ $staffRows->isEmpty() ? '' : 'display:none;' }}">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                {{ $canBrowseAllCompanies && ! $companyId ? 'Pilih perusahaan dulu.' : 'Belum ada karyawan di bawah kamu.' }}
            </p>
        </div>

        <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-top:14px;">
            <button type="button" class="list-prev btn-ghost" style="padding:6px 14px;font-size:12px;">
                <i class="fa-solid fa-chevron-left" style="font-size:10px;"></i> Sebelumnya
            </button>
            <ul class="pagination" style="display:flex;flex-wrap:wrap;gap:4px;list-style:none;padding:0;margin:0;"></ul>
            <button type="button" class="list-next btn-ghost" style="padding:6px 14px;font-size:12px;">
                Selanjutnya <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i>
            </button>
        </div>
    </div>
</div>

</div>
