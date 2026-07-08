<div>
@push('head')
    @filamentStyles
    {{-- @filamentStyles cuma register asset yang scoped ke Panel aktif; CSS utama Filament
         (fi-* classes, dipakai table/button/section) di-load manual karena halaman ini di luar Panel --}}
    <link rel="stylesheet" href="{{ asset('css/filament/filament/app.css') }}">
@endpush

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
            <p style="font-size:13px;color:#64748b;margin:0;">
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
                @endphp
                <a href="{{ route('my-staff.show', ['record' => $employee]) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon neutral" style="border-radius:50%;font-size:12px;font-weight:700;">{{ $initials ?: '?' }}</div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:#1e293b;">{{ $employee->name }}</p>
                        <p style="font-size:11px;color:#64748b;margin-top:2px;">
                            {{ $employee->nip ?: 'NIP belum diisi' }}
                            @if ($employee->profile?->jobPosition?->name)
                                &middot; {{ $employee->profile->jobPosition->name }}
                            @endif
                        </p>
                        @if ($employee->profile?->department?->name || ($canBrowseAllCompanies && $employee->company?->name))
                            <p style="font-size:11px;color:#94a3b8;margin-top:2px;">
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
            {{ $employees->links() }}
        </div>
    @endif
</div>

<!-- DESKTOP: Filament Table -->
<div class="hidden md:block">
    {{ $this->table }}
</div>

@push('scripts')
    @filamentScripts
@endpush
</div>
