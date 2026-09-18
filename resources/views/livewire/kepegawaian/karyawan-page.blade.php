<div>
@php $employees = $this->employees; @endphp
<x-page-header title="Karyawan" subtitle="Data seluruh karyawan perusahaan" />

<div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:16px;">
    @can(\App\Enum\CustomPermissionEnum::IMPORT_KARYAWAN->value)
        <a href="{{ route('kepegawaian.karyawan.import') }}" wire:navigate class="btn-ghost">
            <i class="fa-solid fa-file-import"></i> Import Akun
        </a>
    @endcan
    @can(\App\Enum\CustomPermissionEnum::EXPORT_DATA_KARYAWAN->value)
        <button type="button" wire:click="export" wire:loading.attr="disabled" class="btn-ghost">
            <i class="fa-solid fa-file-export"></i> Export Excel
        </button>
    @endcan
    <a href="{{ route('kepegawaian.karyawan.create') }}" wire:navigate class="btn-primary">
        <i class="fa-solid fa-user-plus"></i> Tambah Karyawan
    </a>
</div>

{{-- Filter status - 3 konsep beda, jangan dicampur:
     - Aktif: is_active=true, belum dihapus (default)
     - Tidak Aktif: is_active=false tapi masih ada di sistem (toggle Edit Karyawan) - bisa
       diaktifkan lagi kapan saja
     - Arsip: soft-deleted (dihapus), cuma bisa dipulihkan, bukan diaktifkan langsung.
     Sengaja kecil/plain (bukan .section-nav-btn), ini cuma filter sekunder, bukan navigasi utama. --}}
{{-- Total ngikut baseQuery() (filter company/departemen/divisi/cari + tab status aktif) -
sama sumbernya dengan tabel/list di bawah lewat $this->employees (paginator), bukan query count
terpisah yang bisa menyimpang. --}}
<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;">
    <div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;">
        <button type="button" wire:click="setStatus('aktif')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === 'aktif' ? 'var(--brand-success)' : 'transparent' }};color:{{ $status === 'aktif' ? '#fff' : 'var(--text-muted)' }};box-shadow:{{ $status === 'aktif' ? 'var(--shadow-card)' : 'none' }};">Aktif</button>
        <button type="button" wire:click="setStatus('tidak_aktif')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === 'tidak_aktif' ? '#b45309' : 'transparent' }};color:{{ $status === 'tidak_aktif' ? '#fff' : 'var(--text-muted)' }};box-shadow:{{ $status === 'tidak_aktif' ? 'var(--shadow-card)' : 'none' }};">Tidak Aktif</button>
        <button type="button" wire:click="setStatus('arsip')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $status === 'arsip' ? '#64748b' : 'transparent' }};color:{{ $status === 'arsip' ? '#fff' : 'var(--text-muted)' }};box-shadow:{{ $status === 'arsip' ? 'var(--shadow-card)' : 'none' }};">Arsip</button>
    </div>
    <span class="badge badge-cancelled"><i class="fa-solid fa-users"></i> {{ $employees->total() }} karyawan</span>
</div>

<div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
    @if ($canBrowseAllCompanies)
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

    <div style="min-width:220px;">
        <label class="form-label">Departemen</label>
        <select wire:model.live="departmentId" class="form-input" @disabled(! $companyId)>
            <option value="">Semua departemen</option>
            @foreach ($this->departmentOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <div style="min-width:220px;">
        <label class="form-label">Divisi</label>
        <select wire:model.live="divisionId" class="form-input" @disabled(! $departmentId)>
            <option value="">Semua divisi</option>
            @foreach ($this->divisionOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <div style="min-width:220px;flex:1;">
        <label class="form-label">Cari</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama, NIP, jabatan..." style="padding-left:38px;">
        </div>
    </div>
</div>

<!-- MOBILE: card manual -->
<div class="md:hidden">
    @if ($employees->isEmpty())
        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                Belum ada karyawan yang cocok.
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
                @if ($status === 'tidak_aktif' || $status === 'arsip')
                    <div class="pwa-list-row">
                        <div class="pwa-tile-icon neutral" style="border-radius:50%;font-size:12px;font-weight:700;">{{ $initials ?: '?' }}</div>
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $employee->name }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $employee->nip ?: 'NIP belum diisi' }}</p>
                        </div>
                        @if ($status === 'tidak_aktif')
                            <button type="button" wire:click="activate('{{ $employee->id }}')" wire:confirm="Aktifkan lagi karyawan ini?" class="btn-ghost" style="padding:5px 9px;font-size:11px;color:var(--brand-success);" title="Aktifkan">
                                <i class="fa-solid fa-user-check"></i>
                            </button>
                        @else
                            <button type="button" wire:click="restore('{{ $employee->id }}')" wire:confirm="Pulihkan karyawan ini?" class="btn-ghost" style="padding:5px 9px;font-size:11px;color:var(--brand-success);" title="Pulihkan">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                            @can('forceDelete', $employee)
                                <button type="button" wire:click="forceDeleteEmployee('{{ $employee->id }}')" wire:confirm="Hapus PERMANEN karyawan ini? Data tidak bisa dipulihkan lagi setelah ini." class="btn-ghost" style="padding:5px 9px;font-size:11px;color:#dc2626;" title="Hapus Permanen">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            @endcan
                        @endif
                    </div>
                @else
                    <a href="{{ route('karyawan.show', ['record' => $employee, 'from' => 'karyawan']) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
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
                @endif
            @endforeach
        </div>

        <div style="margin-top:16px;">
            {{ $employees->links('livewire.partials.pagination') }}
        </div>
    @endif
</div>

<!-- DESKTOP: tabel -->
<div class="hidden md:block">
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
                    <tr wire:key="'karyawan-'.{{ $employee->id }}" style="border-bottom:1px solid var(--border-soft);">
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
                            @if ($status === 'tidak_aktif')
                                <button type="button" wire:click="activate('{{ $employee->id }}')" wire:confirm="Aktifkan lagi karyawan ini?" class="btn-ghost" style="padding:5px 10px;font-size:11px;color:var(--brand-success);" title="Aktifkan">
                                    <i class="fa-solid fa-user-check"></i>
                                </button>
                            @elseif ($status === 'arsip')
                                <button type="button" wire:click="restore('{{ $employee->id }}')" wire:confirm="Pulihkan karyawan ini?" class="btn-ghost" style="padding:5px 10px;font-size:11px;color:var(--brand-success);" title="Pulihkan">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </button>
                                @can('forceDelete', $employee)
                                    <button type="button" wire:click="forceDeleteEmployee('{{ $employee->id }}')" wire:confirm="Hapus PERMANEN karyawan ini? Data tidak bisa dipulihkan lagi setelah ini." class="btn-ghost" style="padding:5px 10px;font-size:11px;color:#dc2626;" title="Hapus Permanen">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                @endcan
                            @else
                                <a href="{{ route('karyawan.show', ['record' => $employee, 'from' => 'karyawan']) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Lihat">
                                    <i class="fa-solid fa-chart-bar"></i>
                                </a>
                                <a href="{{ route('kepegawaian.karyawan.edit', ['record' => $employee]) }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($employees->isEmpty())
        <div style="padding:32px 16px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                Belum ada karyawan yang cocok.
            </p>
        </div>
    @endif

    <div style="margin-top:16px;">
        {{ $employees->links('livewire.partials.pagination') }}
    </div>
</div>

</div>
