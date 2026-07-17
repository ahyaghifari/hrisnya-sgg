@php
    $rows = $this->componentRows;
    $earningTotal = collect($rows['earnings'])->sum('amount');
    $deductionTotal = collect($rows['deductions'])->sum('amount');
@endphp

<div class="p-4 md:p-6">
    <div style="display:flex;flex-direction:column;gap:16px;max-width:760px;">
        <a href="{{ route('keuangan.payroll') }}" wire:navigate style="font-size:12.5px;color:var(--text-muted);text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Kembali ke daftar Payroll</a>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;">
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Karyawan</p>
                    <p style="font-size:13.5px;font-weight:700;margin:0;">{{ $payroll->user?->name ?? '-' }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">NIP {{ $payroll->user?->nip ?? '-' }}</p>
                </div>
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
                    <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $payroll->is_paid ? 'rgba(4,124,84,0.1)' : 'rgba(217,119,6,0.1)' }};color:{{ $payroll->is_paid ? '#047C54' : '#d97706' }};">
                        {{ $payroll->is_paid ? 'Sudah Dibayar' : 'Belum Dibayar' }}
                    </span>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:18px;padding-top:18px;border-top:1px dashed var(--border);">
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Gaji Kotor</p>
                    <p style="font-size:15px;font-weight:700;margin:0;">Rp{{ number_format($payroll->gross_salary, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Total Potongan</p>
                    <p style="font-size:15px;font-weight:700;margin:0;color:#dc2626;">Rp{{ number_format($payroll->total_deductions, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Gaji Bersih</p>
                    <p style="font-size:16px;font-weight:800;margin:0;color:#047C54;">Rp{{ number_format($payroll->net_salary, 0, ',', '.') }}</p>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;">
                @unless ($payroll->is_paid)
                    <button type="button" wire:click="markAsPaid" wire:confirm="Tandai payroll ini sudah dibayar?" class="btn-primary" style="padding:9px 18px;font-size:12.5px;">
                        <i class="fa-solid fa-check"></i> Tandai Sudah Dibayar
                    </button>
                @endunless
                <a href="{{ route('keuangan.payroll.preview', $payroll->id) }}" target="_blank" class="btn-ghost" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-eye"></i> Preview Slip Gaji
                </a>
                <button type="button" wire:click="downloadSlip" class="btn-ghost" style="padding:9px 18px;font-size:12.5px;">
                    <i class="fa-solid fa-download"></i> Download Slip Gaji
                </button>
            </div>
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">
                <div style="padding-right:16px;border-right:1px dashed var(--border);">
                    <p style="display:flex;align-items:center;gap:6px;font-size:10.5px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#047C54;margin:0 0 10px;">
                        <i class="fa-solid fa-arrow-trend-up"></i> Pendapatan
                    </p>
                    <div style="display:flex;flex-direction:column;gap:7px;">
                        @forelse ($rows['earnings'] as $row)
                            <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;">
                                <span style="color:{{ $row['hasValue'] ? 'var(--text-body)' : 'var(--text-faint)' }};">
                                    {{ $row['label'] }}
                                    @if ($row['note'])
                                        <span style="font-size:10.5px;color:var(--text-faint);">({{ $row['note'] }})</span>
                                    @endif
                                </span>
                                <span style="font-weight:{{ $row['hasValue'] ? '700' : '500' }};color:{{ $row['hasValue'] ? '#047C54' : 'var(--text-faint)' }};white-space:nowrap;">Rp{{ number_format($row['amount'], 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p style="font-size:11.5px;color:var(--text-faint);margin:0;">Tidak ada komponen pendapatan.</p>
                        @endforelse
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;font-weight:700;margin-top:10px;padding-top:8px;border-top:1px solid var(--border-soft);">
                        <span>Subtotal</span>
                        <span style="color:#047C54;">Rp{{ number_format($earningTotal, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div style="padding-left:16px;">
                    <p style="display:flex;align-items:center;gap:6px;font-size:10.5px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#dc2626;margin:0 0 10px;">
                        <i class="fa-solid fa-arrow-trend-down"></i> Potongan
                    </p>
                    <div style="display:flex;flex-direction:column;gap:7px;">
                        @forelse ($rows['deductions'] as $row)
                            <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;">
                                <span style="color:{{ $row['hasValue'] ? 'var(--text-body)' : 'var(--text-faint)' }};">
                                    {{ $row['label'] }}
                                    @if ($row['note'])
                                        <span style="font-size:10.5px;color:var(--text-faint);">({{ $row['note'] }})</span>
                                    @endif
                                </span>
                                <span style="font-weight:{{ $row['hasValue'] ? '700' : '500' }};color:{{ $row['hasValue'] ? '#dc2626' : 'var(--text-faint)' }};white-space:nowrap;">Rp{{ number_format($row['amount'], 0, ',', '.') }}</span>
                            </div>
                        @empty
                            <p style="font-size:11.5px;color:var(--text-faint);margin:0;">Tidak ada komponen potongan.</p>
                        @endforelse
                    </div>
                    <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;font-weight:700;margin-top:10px;padding-top:8px;border-top:1px solid var(--border-soft);">
                        <span>Subtotal</span>
                        <span style="color:#dc2626;">Rp{{ number_format($deductionTotal, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
