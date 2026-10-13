<div>
<x-page-header title="Dashboard Kepegawaian" subtitle="Headcount, kelengkapan data, dan perubahan status karyawan" />

@if ($this->canBrowseAllCompanies)
    <div class="mb-4" style="max-width:280px;">
        <label class="form-label">Perusahaan</label>
        <select wire:model.live="companyId" class="form-input">
            <option value="">Semua perusahaan</option>
            @foreach ($this->companyOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>
@endif

{{-- HERO — satu angka utama, bukan tumpukan info. Total Karyawan jadi anchor halaman, klik buat lihat breakdown per departemen. --}}
<button type="button" wire:click="openModal('total')" class="page-hero" style="margin-bottom:16px;width:100%;text-align:left;border:none;cursor:pointer;font-family:inherit;">
    <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
    <div style="position:relative;z-index:1;display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div>
            <p style="font-size:12px;color:rgba(255,255,255,0.75);font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 6px;">Total Karyawan Aktif</p>
            <span style="font-size:44px;font-weight:800;color:#fff;letter-spacing:-1.5px;line-height:1;">{{ $this->totalActiveEmployees }}</span>
        </div>
        <span style="display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.14);color:#fff;border:1px solid rgba(255,255,255,0.22);border-radius:10px;padding:8px 14px;font-size:12px;font-weight:600;">
            <i class="fa-solid fa-chart-pie"></i> Lihat breakdown
        </span>
    </div>
</button>

{{-- SIGNATURE — donut kelengkapan data (CSS conic-gradient murni) berdampingan 3 tile ringkas, semua bisa diklik buat detail. --}}
<div class="grid grid-cols-1 md:grid-cols-3" style="gap:14px;margin-bottom:16px;align-items:stretch;">
    @php $pct = $this->dataCompletenessPercent; @endphp
    <button type="button" wire:click="openModal('completeness')" style="background:var(--surface);border:none;cursor:pointer;font-family:inherit;text-align:left;border-radius:18px;box-shadow:var(--shadow-card);padding:20px;display:flex;align-items:center;gap:18px;">
        <div style="position:relative;width:96px;height:96px;flex-shrink:0;">
            <div style="width:100%;height:100%;border-radius:50%;background:conic-gradient(#0f766e {{ $pct * 3.6 }}deg, var(--border) 0deg);"></div>
            <div style="position:absolute;inset:9px;border-radius:50%;background:var(--surface);display:flex;flex-direction:column;align-items:center;justify-content:center;">
                <span style="font-size:20px;font-weight:800;color:#0f766e;letter-spacing:-0.5px;">{{ $pct }}%</span>
            </div>
        </div>
        <div>
            <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Kelengkapan Data Karyawan</p>
            <p style="font-size:11px;color:var(--text-muted);margin:0;line-height:1.5;">Rata-rata field inti (NIK, KK, BPJS, kontak, rekening) yang sudah terisi per karyawan.</p>
        </div>
    </button>

    <div style="grid-column:span 1;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <button type="button" wire:click="openModal('new')" style="text-align:left;border:none;cursor:pointer;font-family:inherit;background:linear-gradient(135deg, #047C54 0%, #024732 100%);border-radius:18px;padding:16px;position:relative;overflow:hidden;">
            <div style="position:absolute;top:-16px;right:-16px;width:70px;height:70px;border-radius:50%;background:#fff;opacity:0.08;"></div>
            <i class="fa-solid fa-user-plus" style="color:rgba(255,255,255,0.85);font-size:13px;position:relative;"></i>
            <p style="font-size:22px;font-weight:800;color:#fff;margin:8px 0 2px;position:relative;">{{ $this->newEmployeesThisMonth }}</p>
            <p style="font-size:10.5px;color:rgba(255,255,255,0.8);margin:0;position:relative;">Karyawan baru bulan ini</p>
        </button>

        <button type="button" wire:click="openModal('vacant')" style="text-align:left;border:none;cursor:pointer;font-family:inherit;background:linear-gradient(135deg, #9a3412 0%, #ea580c 100%);border-radius:18px;padding:16px;position:relative;overflow:hidden;">
            <div style="position:absolute;top:-16px;right:-16px;width:70px;height:70px;border-radius:50%;background:#fff;opacity:0.08;"></div>
            <i class="fa-solid fa-user-clock" style="color:rgba(255,255,255,0.85);font-size:13px;position:relative;"></i>
            <p style="font-size:22px;font-weight:800;color:#fff;margin:8px 0 2px;position:relative;">{{ $this->vacantPositionsCount }}</p>
            <p style="font-size:10.5px;color:rgba(255,255,255,0.85);margin:0;position:relative;">Jabatan kurang orang</p>
        </button>
    </div>

    <button type="button" wire:click="openModal('pending')" style="text-align:left;border:none;cursor:pointer;font-family:inherit;background:linear-gradient(135deg, #92400e 0%, #d97706 100%);border-radius:18px;padding:20px;position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:center;">
        <div style="position:absolute;top:-24px;right:-24px;width:100px;height:100px;border-radius:50%;background:#fff;opacity:0.08;"></div>
        <div style="position:relative;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <p style="font-size:32px;font-weight:800;color:#fff;margin:0;letter-spacing:-1px;">{{ $this->pendingTransitionsCount }}</p>
                <p style="font-size:11.5px;color:rgba(255,255,255,0.85);margin:2px 0 0;">Perubahan status menunggu persetujuan</p>
            </div>
            <i class="fa-solid fa-arrow-right" style="color:rgba(255,255,255,0.8);font-size:14px;"></i>
        </div>
    </button>
</div>

{{-- Kelengkapan dokumen nakes: jumlah karyawan nakes per masalah STR/SIP, klik buat daftar nama. --}}
@php $nakes = $this->nakesCompleteness; @endphp
@if ($nakes['total'] > 0)
    <button type="button" wire:click="openModal('nakes')" style="display:block;width:100%;text-align:left;border:none;cursor:pointer;font-family:inherit;background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:16px;flex-shrink:0;">
            <div style="position:relative;width:96px;height:96px;flex-shrink:0;">
                <div style="width:100%;height:100%;border-radius:50%;background:conic-gradient(#0f766e {{ $nakes['percent'] * 3.6 }}deg, var(--border) 0deg);"></div>
                <div style="position:absolute;inset:9px;border-radius:50%;background:var(--surface);display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:20px;font-weight:800;color:#0f766e;letter-spacing:-0.5px;">{{ $nakes['percent'] }}%</span>
                </div>
            </div>
            <div>
                <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;"><i class="fa-solid fa-user-doctor" style="color:var(--brand);margin-right:6px;"></i>Kelengkapan Dokumen Nakes</h3>
                <p style="font-size:11px;color:var(--text-muted);margin:0;line-height:1.5;max-width:200px;">{{ $nakes['rows']->count() }} dari {{ $nakes['total'] }} nakes perlu tindak lanjut (STR &amp; SIP).</p>
            </div>
        </div>
        <div style="flex:1;min-width:260px;display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;">
            @foreach (\App\Livewire\Kepegawaian\KepegawaianDashboardPage::NAKES_ISSUES as $key => $label)
                <div style="border:1px solid var(--border);border-radius:12px;padding:12px;">
                    <p style="font-size:22px;font-weight:800;margin:0;color:{{ $nakes['counts'][$key] > 0 ? 'var(--danger-text)' : 'var(--text-heading)' }};">{{ $nakes['counts'][$key] }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $label }}</p>
                </div>
            @endforeach
        </div>
        </div>
    </button>
@endif

<div class="grid grid-cols-1 md:grid-cols-2" style="gap:16px;align-items:start;">
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Distribusi Karyawan per Jabatan</h3>

        @forelse ($this->headcountByPosition as $row)
            <button type="button" wire:click="openPositionModal('{{ $row['name'] }}')" style="display:block;width:100%;text-align:left;border:none;background:none;cursor:pointer;font-family:inherit;padding:0;margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                    <span style="color:var(--text-body);font-weight:600;">{{ $row['name'] }}</span>
                    <span style="color:var(--text-muted);">{{ $row['count'] }}</span>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:{{ $row['percent'] }}%;background:var(--brand);"></div></div>
            </button>
        @empty
            <p style="font-size:12.5px;color:var(--text-muted);">Belum ada data karyawan dengan jabatan terisi.</p>
        @endforelse
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Perubahan Status Menunggu Persetujuan</h3>
            <a href="{{ route('kepegawaian.mutasi-karyawan') }}" wire:navigate style="font-size:11px;color:var(--brand);text-decoration:none;font-weight:600;">Lihat semua</a>
        </div>

        @forelse ($this->pendingTransitions as $transition)
            <a href="{{ route('kepegawaian.mutasi-karyawan.show', $transition) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                <div class="pwa-tile-icon neutral"><i class="fa-solid fa-right-left"></i></div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $transition->user?->name ?? '-' }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ \App\Enum\UserTransitionTypeEnum::from($transition->transition_type)->label() }} &middot; {{ $transition->created_at?->translatedFormat('d M Y') }}</p>
                </div>
                <span class="badge badge-pending">Menunggu</span>
            </a>
        @empty
            <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada pengajuan yang menunggu persetujuan.</p>
        @endforelse
    </div>
</div>

{{-- ===================== MODALS ===================== --}}

@if ($activeModal === 'total')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Total Karyawan per Departemen</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $this->totalActiveEmployees }} karyawan aktif secara keseluruhan.</p>

            @forelse ($this->headcountByDepartment as $row)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                    <span style="font-size:12.5px;color:var(--text-body);">{{ $row['name'] }}</span>
                    <span style="font-size:12.5px;font-weight:700;color:var(--text-heading);">{{ $row['count'] }}</span>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Belum ada karyawan dengan departemen terisi.</p>
            @endforelse

            <div style="margin-top:16px;text-align:right;">
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif

@if ($activeModal === 'completeness')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Karyawan dengan Data Belum Lengkap</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Diurutkan dari yang paling banyak field kosong. Maks. 20 ditampilkan.</p>

            @forelse ($this->incompleteEmployees as $row)
                <div style="padding:10px 0;border-bottom:1px solid var(--border-soft);">
                    <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0 0 4px;">{{ $row['user']->name }}</p>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        @foreach ($row['missing'] as $label)
                            <span class="badge badge-rejected" style="font-size:10px;">{{ $label }}</span>
                        @endforeach
                    </div>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Semua karyawan sudah lengkap datanya. 🎉</p>
            @endforelse

            <div style="margin-top:16px;text-align:right;">
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif

@if ($activeModal === 'nakes')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box modal-box-lg" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Dokumen Nakes Perlu Tindak Lanjut</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Diurutkan dari yang paling banyak masalah. Hanya dokumen STR/SIP aktif yang dihitung.</p>

            @forelse ($this->nakesCompleteness['rows']->take(50) as $row)
                <div style="padding:10px 0;border-bottom:1px solid var(--border-soft);">
                    <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0 0 4px;">{{ $row['user']->name }}</p>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        @foreach ($row['issues'] as $issue)
                            <span class="badge badge-rejected" style="font-size:10px;">{{ \App\Livewire\Kepegawaian\KepegawaianDashboardPage::NAKES_ISSUES[$issue] }}</span>
                        @endforeach
                    </div>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Semua dokumen nakes sudah lengkap dan berlaku.</p>
            @endforelse

            <div style="margin-top:16px;text-align:right;">
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif

@if ($activeModal === 'new')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Karyawan Baru Bulan Ini</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Bergabung antara {{ now()->startOfMonth()->translatedFormat('d M Y') }} &ndash; {{ now()->endOfMonth()->translatedFormat('d M Y') }}.</p>

            @forelse ($this->newEmployeesList as $employee)
                <div style="padding:8px 0;border-bottom:1px solid var(--border-soft);">
                    <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $employee->name }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $employee->profile?->jobPosition?->name ?? '-' }} &middot; {{ $employee->profile?->joining_date ? \Illuminate\Support\Carbon::parse($employee->profile->joining_date)->translatedFormat('d M Y') : '-' }}</p>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Belum ada karyawan baru bulan ini.</p>
            @endforelse

            <div style="margin-top:16px;text-align:right;">
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif

@if ($activeModal === 'vacant')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Jabatan yang Kurang Orang</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Kuota jabatan yang belum terisi penuh oleh karyawan aktif.</p>

            @forelse ($this->vacantPositionsList as $row)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                    <div>
                        <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $row['name'] }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">Terisi {{ $row['filled'] }} dari {{ $row['quota'] }}</p>
                    </div>
                    <span class="badge badge-rejected">-{{ $row['vacant'] }}</span>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Semua jabatan sudah terisi penuh.</p>
            @endforelse

            <div style="margin-top:16px;display:flex;justify-content:space-between;">
                <a href="{{ route('kepegawaian.permintaan') }}" wire:navigate class="btn-ghost" style="font-size:12px;"><i class="fa-solid fa-right-left"></i> Ajukan Permintaan Tenaga Kerja</a>
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif

@if ($activeModal === 'pending')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Perubahan Status Menunggu Persetujuan</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Maks. 20 ditampilkan, urut dari yang terbaru.</p>

            @forelse ($this->pendingTransitionsForModal as $transition)
                <a href="{{ route('kepegawaian.mutasi-karyawan.show', $transition) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-right-left"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $transition->user?->name ?? '-' }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ \App\Enum\UserTransitionTypeEnum::from($transition->transition_type)->label() }} &middot; {{ $transition->created_at?->translatedFormat('d M Y') }}</p>
                    </div>
                </a>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada pengajuan yang menunggu persetujuan.</p>
            @endforelse

            <div style="margin-top:16px;text-align:right;">
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif

@if ($activeModal === 'position')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 14px;">Karyawan Jabatan {{ $selectedPositionName }}</h3>

            @forelse ($this->employeesInSelectedPosition as $employee)
                <div style="padding:8px 0;border-bottom:1px solid var(--border-soft);">
                    <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $employee->name }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $employee->nip }}</p>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada karyawan di jabatan ini.</p>
            @endforelse

            <div style="margin-top:16px;text-align:right;">
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif
</div>
