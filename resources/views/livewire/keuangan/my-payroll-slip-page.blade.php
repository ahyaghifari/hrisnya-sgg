@php
    $rows = $this->componentRows;
    $earningTotal = collect($rows['earnings'])->sum('amount');
    $deductionTotal = collect($rows['deductions'])->sum('amount');
@endphp

<div class="p-4 md:p-6">
    <style>
        .payroll-slip-columns { display:grid; grid-template-columns:1fr; gap:20px; }
        @media (min-width: 640px) {
            .payroll-slip-columns { grid-template-columns:1fr 1fr; gap:0; }
            .payroll-slip-col-left { padding-right:16px; border-right:1px dashed var(--border); }
            .payroll-slip-col-right { padding-left:16px; }
        }
    </style>

    <div style="display:flex;flex-direction:column;gap:16px;">
        <a href="{{ route('profil-saya') }}" wire:navigate style="font-size:12.5px;color:var(--text-muted);text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Kembali ke Profil Saya</a>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;max-width:760px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;">
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Perusahaan</p>
                    <p style="font-size:13.5px;font-weight:700;margin:0;">{{ $payroll->payrollPeriod->company?->name ?? '-' }}</p>
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Periode</p>
                    <p style="font-size:13.5px;font-weight:700;margin:0;">{{ $payroll->payrollPeriod->start_date->format('d M Y') }} - {{ $payroll->payrollPeriod->end_date->format('d M Y') }}</p>
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Status</p>
                    <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:rgba(4,124,84,0.1);color:#047C54;">
                        Sudah Dibayar
                    </span>
                    @if ($payroll->payrollPeriod->payment_date)
                        <p style="font-size:10.5px;color:var(--text-muted);margin:4px 0 0;">
                            <i class="fa-solid fa-calendar-check"></i> {{ $payroll->payrollPeriod->payment_date->translatedFormat('d M Y') }}
                        </p>
                    @endif
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-top:18px;padding-top:18px;border-top:1px dashed var(--border);">
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Gaji Kotor</p>
                    <p style="font-size:15px;font-weight:700;margin:0;">{{ $this->rp($payroll->gross_salary) }}</p>
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Total Potongan</p>
                    <p style="font-size:15px;font-weight:700;margin:0;color:#dc2626;">{{ $this->rp($payroll->total_deductions) }}</p>
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Gaji Bersih</p>
                    <p style="font-size:16px;font-weight:800;margin:0;color:#047C54;">{{ $this->rp($payroll->net_salary) }}</p>
                </div>
            </div>

            <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
                <button type="button" wire:click="toggleMoney" class="btn-ghost" style="padding:6px 12px;font-size:11px;">
                    <i class="fa-solid {{ $hideMoney ? 'fa-eye' : 'fa-eye-slash' }}"></i> {{ $hideMoney ? 'Tampilkan Angka' : 'Sembunyikan Angka' }}
                </button>
                <a href="{{ route('profil-saya.penggajian.preview', $payroll->id) }}" target="_blank" class="btn-ghost" style="padding:6px 12px;font-size:11px;">
                    <i class="fa-solid fa-eye"></i> Preview Slip Gaji
                </a>
                <button type="button" wire:click="downloadSlip" class="btn-ghost" style="padding:6px 12px;font-size:11px;">
                    <i class="fa-solid fa-download"></i> Download Slip Gaji
                </button>
            </div>
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
            <div class="payroll-slip-columns">
                <div class="payroll-slip-col-left">
                    <p style="display:flex;align-items:center;gap:6px;font-size:10.5px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#047C54;margin:0 0 10px;">
                        <i class="fa-solid fa-arrow-trend-up"></i> Pendapatan
                    </p>
                    <div style="display:flex;flex-direction:column;gap:7px;">
                        @forelse ($rows['earnings'] as $row)
                            <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;">
                                <span style="color:{{ $row['hasValue'] ? 'var(--text-body)' : 'var(--text-faint)' }};">{{ $row['label'] }}</span>
                                <span style="white-space:nowrap;text-align:right;">
                                    <span style="font-weight:{{ $row['hasValue'] ? '700' : '500' }};color:{{ $row['hasValue'] ? '#047C54' : 'var(--text-faint)' }};">{{ $this->rp($row['amount']) }}</span>
                                    @if ($row['note'] && ! $hideMoney)
                                        <span style="display:block;font-size:10px;font-weight:400;color:var(--text-faint);">({{ $row['note'] }})</span>
                                    @endif
                                </span>
                            </div>
                        @empty
                            <p style="font-size:11.5px;color:var(--text-faint);margin:0;">Tidak ada komponen pendapatan.</p>
                        @endforelse
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;font-weight:700;margin-top:10px;padding-top:8px;border-top:1px solid var(--border-soft);">
                        <span>Subtotal</span>
                        <span style="color:#047C54;">{{ $this->rp($earningTotal) }}</span>
                    </div>
                </div>

                <div class="payroll-slip-col-right">
                    <p style="display:flex;align-items:center;gap:6px;font-size:10.5px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#dc2626;margin:0 0 10px;">
                        <i class="fa-solid fa-arrow-trend-down"></i> Potongan
                    </p>
                    <div style="display:flex;flex-direction:column;gap:7px;">
                        @forelse ($rows['deductions'] as $row)
                            <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;">
                                <span style="color:{{ $row['hasValue'] ? 'var(--text-body)' : 'var(--text-faint)' }};">{{ $row['label'] }}</span>
                                <span style="white-space:nowrap;text-align:right;">
                                    <span style="font-weight:{{ $row['hasValue'] ? '700' : '500' }};color:{{ $row['hasValue'] ? '#dc2626' : 'var(--text-faint)' }};">{{ $this->rp($row['amount']) }}</span>
                                    @if ($row['note'] && ! $hideMoney)
                                        <span style="display:block;font-size:10px;font-weight:400;color:var(--text-faint);">({{ $row['note'] }})</span>
                                    @endif
                                </span>
                            </div>
                        @empty
                            <p style="font-size:11.5px;color:var(--text-faint);margin:0;">Tidak ada komponen potongan.</p>
                        @endforelse
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;font-weight:700;margin-top:10px;padding-top:8px;border-top:1px solid var(--border-soft);">
                        <span>Subtotal</span>
                        <span style="color:#dc2626;">{{ $this->rp($deductionTotal) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
