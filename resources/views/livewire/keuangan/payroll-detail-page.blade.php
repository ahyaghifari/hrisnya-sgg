@php
    $rows = $this->componentRows;
    $earningTotal = collect($rows['earnings'])->sum('amount');
    $deductionTotal = collect($rows['deductions'])->sum('amount');
@endphp

<div class="p-4 md:p-6">
    <div style="display:flex;flex-direction:column;gap:16px;">
        <a href="{{ route('keuangan.payroll') }}" wire:navigate style="font-size:12.5px;color:var(--text-muted);text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Kembali ke daftar Payroll</a>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:18px;max-width:760px;">
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
                <div>
                    <p style="font-size:10.5px;color:var(--text-faint);margin:0 0 2px;text-transform:uppercase;letter-spacing:0.03em;">Terakhir Digenerate</p>
                    <p style="font-size:12.5px;font-weight:700;margin:0;">{{ $payroll->generatedBy?->name ?? '-' }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $payroll->generated_at?->translatedFormat('d M Y H:i') ?? '-' }}</p>
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

            <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
                @if (!$payroll->is_paid && $this->canEditPayroll)
                    <button type="button" wire:click="markAsPaid" wire:confirm="Tandai payroll ini sudah dibayar?" class="btn-primary" style="padding:6px 12px;font-size:11px;">
                        <i class="fa-solid fa-check"></i> Tandai Sudah Dibayar
                    </button>
                    <button type="button" wire:click="regeneratePayroll" wire:confirm="Hapus dan hitung ulang payroll karyawan ini saja? Payroll karyawan lain di periode ini tidak terpengaruh." class="btn-ghost" style="padding:6px 12px;font-size:11px;color:#d97706;">
                        <i class="fa-solid fa-arrow-rotate-right"></i> Regenerate Payroll Ini
                    </button>
                @endif
                <a href="{{ route('keuangan.payroll.preview', $payroll->id) }}" target="_blank" class="btn-ghost" style="padding:6px 12px;font-size:11px;">
                    <i class="fa-solid fa-eye"></i> Preview Slip Gaji
                </a>
                <button type="button" wire:click="downloadSlip" class="btn-ghost" style="padding:6px 12px;font-size:11px;">
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
                            <div>
                                <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;">
                                    <span style="color:{{ $row['hasValue'] ? 'var(--text-body)' : 'var(--text-faint)' }};">
                                        {{ $row['label'] }}
                                        @if (($row['component'] ?? null) === \App\Enum\Payroll\PayrollComponentEnum::INSENTIF->value && $this->insentifAktif && ! $payroll->is_paid && $this->insentifManualLines->isEmpty() && $this->canEditPayroll)
                                            <button type="button" wire:click="openManualLineModal('insentif')" title="Tambah Insentif untuk payroll ini" style="border:none;background:#047C54;color:#fff;padding:2px 7px;border-radius:5px;font-size:10px;font-weight:600;cursor:pointer;">+ Tambah</button>
                                        @endif
                                    </span>
                                    <span style="white-space:nowrap;text-align:right;">
                                        <span style="font-weight:{{ $row['hasValue'] ? '700' : '500' }};color:{{ $row['hasValue'] ? '#047C54' : 'var(--text-faint)' }};">Rp{{ number_format($row['amount'], 0, ',', '.') }}</span>
                                        @if ($row['note'])
                                            <span style="display:block;font-size:10px;font-weight:400;color:var(--text-faint);">({{ $row['note'] }})</span>
                                        @endif
                                    </span>
                                </div>

                                @if (($row['component'] ?? null) === \App\Enum\Payroll\PayrollComponentEnum::INSENTIF->value && $this->insentifManualLines->isNotEmpty())
                                    <div style="display:flex;flex-direction:column;gap:3px;margin:4px 0 0 10px;">
                                        @foreach ($this->insentifManualLines as $line)
                                            <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;font-size:11px;color:var(--text-muted);">
                                                <span>Rp{{ number_format($line->amount, 0, ',', '.') }}{{ $line->note ? " ({$line->note})" : '' }}</span>
                                                @if (!$payroll->is_paid && $this->canEditPayroll)
                                                    <span style="display:flex;gap:4px;flex-shrink:0;">
                                                        <button type="button" wire:click="openManualLineModal('insentif', '{{ $line->id }}')" title="Ubah" style="border:1px solid var(--border);background:none;color:#047C54;cursor:pointer;padding:2px 7px;border-radius:5px;font-size:10px;"><i class="fa-solid fa-pen"></i> Ubah</button>
                                                        <button type="button" wire:click="deleteManualLine('{{ $line->id }}')" wire:confirm="Hapus baris Insentif ini?" title="Hapus" style="border:1px solid var(--border);background:none;color:#dc2626;cursor:pointer;padding:2px 7px;border-radius:5px;font-size:10px;"><i class="fa-solid fa-trash"></i> Hapus</button>
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
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
                            <div>
                                <div style="display:flex;justify-content:space-between;gap:8px;font-size:12.5px;">
                                    <span style="color:{{ $row['hasValue'] ? 'var(--text-body)' : 'var(--text-faint)' }};">
                                        {{ $row['label'] }}
                                        @if (($row['component'] ?? null) === \App\Enum\Payroll\PayrollComponentEnum::BON->value && $this->bonAktif && ! $payroll->is_paid && $this->bonManualLines->isEmpty() && $this->canEditPayroll)
                                            <button type="button" wire:click="openManualLineModal('bon')" title="Tambah Bon untuk payroll ini" style="border:none;background:#d97706;color:#fff;padding:2px 7px;border-radius:5px;font-size:10px;font-weight:600;cursor:pointer;">+ Tambah</button>
                                        @endif
                                    </span>
                                    <span style="white-space:nowrap;text-align:right;">
                                        <span style="font-weight:{{ $row['hasValue'] ? '700' : '500' }};color:{{ $row['hasValue'] ? '#dc2626' : 'var(--text-faint)' }};">Rp{{ number_format($row['amount'], 0, ',', '.') }}</span>
                                        @if ($row['note'])
                                            <span style="display:block;font-size:10px;font-weight:400;color:var(--text-faint);">({{ $row['note'] }})</span>
                                        @endif
                                    </span>
                                </div>

                                @if (($row['component'] ?? null) === \App\Enum\Payroll\PayrollComponentEnum::BON->value && $this->bonManualLines->isNotEmpty())
                                    <div style="display:flex;flex-direction:column;gap:3px;margin:4px 0 0 10px;">
                                        @foreach ($this->bonManualLines as $line)
                                            <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;font-size:11px;color:var(--text-muted);">
                                                <span>Rp{{ number_format($line->amount, 0, ',', '.') }}{{ $line->note ? " ({$line->note})" : '' }}</span>
                                                @if (!$payroll->is_paid && $this->canEditPayroll)
                                                    <span style="display:flex;gap:4px;flex-shrink:0;">
                                                        <button type="button" wire:click="openManualLineModal('bon', '{{ $line->id }}')" title="Ubah" style="border:1px solid var(--border);background:none;color:#d97706;cursor:pointer;padding:2px 7px;border-radius:5px;font-size:10px;"><i class="fa-solid fa-pen"></i> Ubah</button>
                                                        <button type="button" wire:click="deleteManualLine('{{ $line->id }}')" wire:confirm="Hapus baris Bon ini?" title="Hapus" style="border:1px solid var(--border);background:none;color:#dc2626;cursor:pointer;padding:2px 7px;border-radius:5px;font-size:10px;"><i class="fa-solid fa-trash"></i> Hapus</button>
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
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

    @php $manualLineLabel = $manualLineComponent === \App\Enum\Payroll\PayrollComponentEnum::BON->value ? 'Bon' : 'Insentif'; @endphp
    @if ($showManualLineModal)
        <div class="modal-overlay" wire:click="closeManualLineModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">{{ $editingManualLineId ? "Ubah {$manualLineLabel}" : "Tambah {$manualLineLabel}" }}</h3>
                <p style="font-size:11.5px;color:var(--text-muted);margin:0 0 14px;">Untuk {{ $payroll->user?->name }} — periode {{ $payroll->payrollPeriod->start_date->format('d M Y') }} - {{ $payroll->payrollPeriod->end_date->format('d M Y') }}.</p>

                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="form-label">Nominal (Rp)</label>
                        <input type="number" step="0.01" class="form-input" wire:model="manualLineAmount" placeholder="0">
                        @error('manualLineAmount') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea class="form-input" rows="2" wire:model="manualLineNote"></textarea>
                    </div>
                </div>

                <p style="font-size:10.5px;color:var(--text-faint);margin-top:10px;">Baru masuk ke payroll ini setelah "Regenerate Payroll Ini" dijalankan.</p>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeManualLineModal" class="btn-ghost">Batal</button>
                    <button type="button" wire:click="saveManualLine" class="btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
