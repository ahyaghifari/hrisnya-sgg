<div>
<x-page-header title="Dashboard Penggajian" subtitle="Ringkasan periode gaji, status pembayaran, insentif, dan koperasi" />

@if ($this->canBrowseAllCompanies)
    <div class="mb-4" style="max-width:280px;">
        <label class="form-label">Perusahaan</label>
        <select wire:model.live="companyId" class="form-input">
            @foreach ($this->companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
    </div>
@endif

@php $period = $this->latestPeriod; @endphp

@if (! $period)
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
        <i class="fa-solid fa-calendar-xmark" style="font-size:22px;color:var(--text-faint);"></i>
        <p style="font-size:12.5px;color:var(--text-muted);margin:12px 0 4px;">Belum ada periode gaji untuk perusahaan ini.</p>
        <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate class="btn-primary" style="margin-top:8px;display:inline-flex;">
            <i class="fa-solid fa-plus"></i> Buat Periode Gaji
        </a>
    </div>
@else
    {{-- SIGNATURE — "statement" ledger look: no hero gradient, no donut. Kop mirip laporan
    keuangan (eyebrow + angka besar tabular-nums bergaris-rangkap, konvensi "grand total" akuntansi),
    dipisah dari body pakai garis putus-putus (motif sobekan kertas struk), lalu bar tersegmentasi
    (bukan conic donut) untuk status bayar. --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px 22px 0;margin-bottom:16px;overflow:hidden;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
            <div>
                <p style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin:0 0 6px;">
                    Total Net Payroll &middot; Periode {{ $period->start_date->translatedFormat('d M') }}&ndash;{{ $period->end_date->translatedFormat('d M Y') }}
                </p>
                <span style="font-family:ui-monospace,'SFMono-Regular',Menlo,monospace;font-size:30px;font-weight:800;color:var(--text-heading);font-variant-numeric:tabular-nums;letter-spacing:-0.5px;padding-bottom:8px;border-bottom:3px double var(--border);display:inline-block;">Rp {{ number_format($this->totalNetPayroll, 0, ',', '.') }}</span>
            </div>
            <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate class="btn-ghost" style="font-size:11.5px;flex-shrink:0;">Kelola Periode</a>
        </div>

        <div style="margin:20px -22px 0;border-top:1.5px dashed var(--border);"></div>

        @php $pct = $this->paidPercent; @endphp
        <button type="button" wire:click="openModal('unpaid')" style="display:block;width:100%;text-align:left;border:none;background:none;cursor:pointer;font-family:inherit;padding:16px 0 20px;">
            <div style="display:flex;justify-content:space-between;font-size:11.5px;margin-bottom:5px;">
                <span style="color:var(--text-body);font-weight:600;">Status Pembayaran</span>
                <span style="color:var(--text-muted);">{{ $pct }}% dibayar &middot; {{ $this->unpaidCount }} dari {{ $this->payrollCount }} slip belum</span>
            </div>
            <div style="display:flex;height:10px;border-radius:5px;overflow:hidden;background:var(--border);">
                <div style="width:{{ $pct }}%;background:#047C54;"></div>
            </div>
        </button>
    </div>

    {{-- Stat strip datar, bukan tile gradient bulat — dua kartu tipis berbatas garis, angka mono. --}}
    <div class="grid grid-cols-1 md:grid-cols-2" style="gap:12px;margin-bottom:16px;">
        @if ($this->insentifMetode === 'master')
            <button type="button" wire:click="openModal('insentif')" style="text-align:left;border:1px solid var(--border);background:var(--surface);border-radius:12px;padding:16px 18px;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 6px;"><i class="fa-solid fa-hand-holding-dollar"></i> Insentif Aktif</p>
                    <span style="font-family:ui-monospace,'SFMono-Regular',Menlo,monospace;font-size:20px;font-weight:800;color:var(--text-heading);">{{ $this->activeIncentiveCount }}</span>
                    <span style="font-size:11px;color:var(--text-muted);"> orang &middot; Rp {{ number_format($this->activeIncentiveTotal, 0, ',', '.') }}</span>
                </div>
                <i class="fa-solid fa-chevron-right" style="color:var(--text-faint);font-size:12px;"></i>
            </button>
        @else
            {{-- Metode 'auto' dihitung live per user saat generate payroll, tidak ada tabel
                 master buat direkap di sini - jangan nunjukkan 0 orang/Rp0 yang menyesatkan. --}}
            <div style="border:1px dashed var(--border);background:var(--surface);border-radius:12px;padding:16px 18px;">
                <p style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 6px;"><i class="fa-solid fa-hand-holding-dollar"></i> Insentif Aktif</p>
                <p style="font-size:11.5px;color:var(--text-faint);margin:0;">Metode Insentif company ini "Otomatis" - dihitung live per karyawan saat payroll digenerate, tidak ada rekap di sini. Lihat per slip gaji.</p>
            </div>
        @endif

        <button type="button" wire:click="openModal('koperasi')" style="text-align:left;border:1px solid var(--border);background:var(--surface);border-radius:12px;padding:16px 18px;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <p style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 6px;"><i class="fa-solid fa-people-group"></i> Anggota Koperasi</p>
                <span style="font-family:ui-monospace,'SFMono-Regular',Menlo,monospace;font-size:20px;font-weight:800;color:var(--text-heading);">{{ $this->activeKoperasiCount }}</span>
                <span style="font-size:11px;color:var(--text-muted);"> orang &middot; Rp {{ number_format($this->activeKoperasiTotal, 0, ',', '.') }}/bln</span>
            </div>
            <i class="fa-solid fa-chevron-right" style="color:var(--text-faint);font-size:12px;"></i>
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2" style="gap:16px;align-items:start;">
        {{-- Komposisi jadi baris ledger (label kiri, nominal mono kanan, garis tipis) — bukan progress bar hijau. --}}
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Komposisi Payroll</h3>
            <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">Top 8 komponen (gaji + potongan) periode ini.</p>

            @forelse ($this->componentBreakdown as $row)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                    <span style="font-size:12.5px;color:var(--text-body);font-weight:600;">{{ $row['label'] }}</span>
                    <span style="font-family:ui-monospace,'SFMono-Regular',Menlo,monospace;font-size:12.5px;font-variant-numeric:tabular-nums;color:{{ $row['type'] === 'deduction' ? '#dc2626' : 'var(--text-heading)' }};">
                        {{ $row['type'] === 'deduction' ? '-' : '' }}Rp {{ number_format($row['total'], 0, ',', '.') }}
                    </span>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Payroll periode ini belum digenerate.</p>
            @endforelse
        </div>

        {{-- Periode terbaru jadi tabel (thead/tbody), bukan kartu list ber-ikon bulat. --}}
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Periode Terbaru</h3>
                <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate style="font-size:11px;color:var(--brand);text-decoration:none;font-weight:600;">Lihat semua</a>
            </div>

            @forelse ($this->recentPeriods as $row)
                <table style="width:100%;border-collapse:collapse;">
                    <tr style="cursor:default;">
                        <td style="padding:8px 0;border-bottom:1px solid var(--border-soft);vertical-align:top;">
                            <a href="{{ route('keuangan.payroll') }}" wire:navigate style="text-decoration:none;font-size:12.5px;font-weight:600;color:var(--text-heading);">
                                {{ $row->start_date->translatedFormat('d M') }} &ndash; {{ $row->end_date->translatedFormat('d M Y') }}
                            </a>
                            <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $row->payrolls_count }} slip</p>
                        </td>
                        <td style="padding:8px 0;border-bottom:1px solid var(--border-soft);text-align:right;vertical-align:top;">
                            @if ($row->processed_at)
                                <span class="badge badge-approved">Digenerate</span>
                            @else
                                <span class="badge badge-pending">Belum</span>
                            @endif
                        </td>
                    </tr>
                </table>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Belum ada periode gaji.</p>
            @endforelse
        </div>
    </div>
@endif

{{-- ===================== MODALS ===================== --}}

@if ($activeModal === 'unpaid')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Payroll Belum Dibayar</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Diurutkan dari net terbesar. Maks. 20 ditampilkan.</p>

            @forelse ($this->unpaidPayrolls as $payroll)
                <a href="{{ route('keuangan.payroll.show', $payroll) }}" wire:navigate style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);text-decoration:none;">
                    <span style="font-size:12.5px;color:var(--text-body);">{{ $payroll->user?->name ?? '-' }}</span>
                    <span style="font-size:12.5px;font-weight:700;color:var(--text-heading);">Rp {{ number_format($payroll->net_salary, 0, ',', '.') }}</span>
                </a>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Semua payroll periode ini sudah dibayar. 🎉</p>
            @endforelse

            <div style="margin-top:16px;display:flex;justify-content:space-between;">
                <a href="{{ route('keuangan.periode-gaji') }}" wire:navigate class="btn-ghost" style="font-size:12px;"><i class="fa-solid fa-list"></i> Lihat Semua Periode</a>
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif

@if ($activeModal === 'insentif')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Insentif Aktif</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Roster insentif metode Master yang masih aktif. Maks. 20 ditampilkan.</p>

            @forelse ($this->activeIncentivesList as $incentive)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                    <div>
                        <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $incentive->user?->name ?? '-' }}</p>
                        @if ($incentive->name)
                            <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $incentive->name }}</p>
                        @endif
                    </div>
                    <span style="font-size:12.5px;font-weight:700;color:var(--text-heading);">Rp {{ number_format($incentive->amount, 0, ',', '.') }}</span>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Belum ada insentif aktif.</p>
            @endforelse

            <div style="margin-top:16px;display:flex;justify-content:space-between;">
                @can('viewAny', \App\Models\Incentive\Incentive::class)
                    <a href="{{ route('keuangan.insentif') }}" wire:navigate class="btn-ghost" style="font-size:12px;"><i class="fa-solid fa-hand-holding-dollar"></i> Kelola Insentif</a>
                @endcan
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif

@if ($activeModal === 'koperasi')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Anggota Koperasi Aktif</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Potongan bulanan berjalan. Maks. 20 ditampilkan.</p>

            @forelse ($this->activeKoperasiList as $member)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                    <span style="font-size:12.5px;color:var(--text-body);">{{ $member->user?->name ?? '-' }}</span>
                    <span style="font-size:12.5px;font-weight:700;color:var(--text-heading);">Rp {{ number_format($member->amount, 0, ',', '.') }}</span>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Belum ada anggota koperasi aktif.</p>
            @endforelse

            <div style="margin-top:16px;display:flex;justify-content:space-between;">
                @can('viewAny', \App\Models\Payroll\UserKoperasi::class)
                    <a href="{{ route('keuangan.koperasi') }}" wire:navigate class="btn-ghost" style="font-size:12px;"><i class="fa-solid fa-people-group"></i> Kelola Koperasi</a>
                @endcan
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif
</div>
