<div
    x-data="{ tab: sessionStorage.getItem('myStaffTab') || 'karyawan', dashboardLoaded: false }"
    x-init="
        $watch('tab', v => sessionStorage.setItem('myStaffTab', v));
        if (tab === 'dashboard') { dashboardLoaded = true; Livewire.dispatch('load-my-staff-dashboard'); }
    "
>
<x-page-header title="MyStaff" />

<div class="section-nav" style="margin-bottom:20px;">
    <button type="button" @click="tab = 'karyawan'" class="section-nav-btn navy" :class="{ 'is-active': tab === 'karyawan' }">
        <i class="fa-solid fa-magnifying-glass"></i> Cari Karyawan
    </button>
    <button type="button" @click="tab = 'dashboard'; if (!dashboardLoaded) { dashboardLoaded = true; Livewire.dispatch('load-my-staff-dashboard'); }" class="section-nav-btn navy" :class="{ 'is-active': tab === 'dashboard' }">
        <i class="fa-solid fa-chart-line"></i> Dashboard
    </button>
</div>

{{-- ===================== TAB: DASHBOARD ===================== --}}
<div x-show="tab === 'dashboard'" x-cloak>
    <div style="margin-bottom:14px;">
        <livewire:pending-approvals-card />
    </div>

    {{-- Dashboard MyStaff - fixed (bukan widget dashboard per jabatan kayak Dashboard Anda),
    scope-nya SELALU bawahan (OrgHierarchyService::subordinates()). Collapsed default, data
    di-query cuma pas widget-nya dibuka pertama kali (instruksi user 2026-09-18) - lihat
    App\Livewire\MyStaff\Concerns\LazyLoadsOnExpand. --}}
    <div class="grid grid-cols-1 lg:grid-cols-2" style="gap:14px;align-items:start;">
        <livewire:my-staff.ringkasan-tim-widget :key="'ringkasan-tim'" />
        <livewire:my-staff.presensi-tim-widget :key="'presensi-tim'" />
        <livewire:my-staff.cuti-tim-widget :key="'cuti-tim'" />
        <livewire:my-staff.kontrak-orientasi-tim-widget :key="'kontrak-orientasi-tim'" />
        <livewire:my-staff.coaching-tim-widget :key="'coaching-tim'" />
    </div>
</div>

{{-- ===================== TAB: CARI KARYAWAN ===================== --}}
<div x-show="tab === 'karyawan'" x-cloak>
    @php $employees = $this->employees; @endphp

    <div style="margin-bottom:14px;max-width:320px;position:relative;">
        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
        <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama, NIP, jabatan..." style="padding-left:38px;">
    </div>

    @if ($employees->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                Belum ada karyawan di bawah kamu.
            </p>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5" style="gap:24px;">
            @foreach ($employees as $employee)
                @php
                    $initials = collect(preg_split('/\s+/', trim($employee->name)))
                        ->map(fn ($w) => mb_substr($w, 0, 1))
                        ->take(2)
                        ->implode('');

                    $photoPath = $employee->profile?->profile_picture[0] ?? null;
                    $photoUrl = $photoPath && is_string($photoPath) ? \Illuminate\Support\Facades\Storage::url($photoPath) : null;
                    $jabatan = $employee->profile?->jobPosition?->name;
                    $departemen = $employee->profile?->department?->name;
                @endphp
                <a href="{{ route('karyawan.show', ['record' => $employee]) }}" wire:navigate wire:key="'staff-card-'.{{ $employee->id }}"
                   class="card-surface staff-card" style="display:flex;flex-direction:column;align-items:center;text-align:center;text-decoration:none;padding:20px 16px;">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="{{ $employee->name }}" class="staff-card-avatar" style="width:64px;height:64px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                    @else
                        <div class="pwa-tile-icon neutral staff-card-avatar" style="width:64px;height:64px;border-radius:50%;font-size:20px;font-weight:700;">{{ $initials ?: '?' }}</div>
                    @endif

                    <p style="font-size:12px;font-weight:700;color:var(--text-heading);margin:12px 0 0;">{{ $employee->name }}</p>
                    <p style="font-size:10px;color:var(--text-muted);margin-top:2px;">{{ $employee->nip ?: 'NIP belum diisi' }}</p>

                    @if ($jabatan || $departemen)
                        <p style="font-size:10px;color:var(--text-body);font-weight:600;margin-top:8px;">
                            {{ $jabatan ?: '-' }}
                            @if ($jabatan && $departemen)
                                &middot;
                            @endif
                            {{ $departemen }}
                        </p>
                    @endif
                </a>
            @endforeach
        </div>

        <div style="margin-top:16px;">
            {{ $employees->links('livewire.partials.pagination') }}
        </div>
    @endif
</div>

</div>
