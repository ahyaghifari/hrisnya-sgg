<div>
    <x-page-header title="Pajak (%)" />

    <div class="mt-4" style="display:flex;flex-direction:column;gap:16px;">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                <x-company-filter :companies="$this->companies" wire:model.live="companyId" />
                <div>
                    <label class="form-label">Periode Gaji</label>
                    <select class="form-input" style="min-width:220px;" wire:model.live="periodId">
                        <option value="">Pilih periode</option>
                        @foreach ($this->periods as $period)
                            <option value="{{ $period->id }}">{{ $period->start_date->format('d M Y') }} — {{ $period->end_date->format('d M Y') }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($periodId)
                <button type="button" wire:click="exportExcel" style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;font-size:12.5px;font-weight:600;border:none;border-radius:12px;cursor:pointer;background:#15803d;color:#fff;">
                    <i class="fa-solid fa-file-excel"></i> Export Excel
                </button>
            @endif
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Karyawan</th>
                        <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Gaji Dasar</th>
                        <th style="text-align:right;padding:10px 14px;white-space:nowrap;">% Kenaikan</th>
                        <th style="text-align:right;padding:10px 14px;white-space:nowrap;">Gaji Pokok</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->payrolls as $payroll)
                        @php
                            [$base, $percent] = \App\Services\Payroll\GajiPokokBreakdownResolver::resolve($payroll);
                            $gajiPokokItem = $payroll->items->firstWhere('source_type', \App\Enum\Payroll\PayrollComponentEnum::GAJI_POKOK->value);
                        @endphp
                        <tr wire:key="'pajak-'.{{ $payroll->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;white-space:nowrap;">
                                <span style="font-weight:600;">{{ $payroll->employee?->nama_lengkap ?? '-' }}</span>
                                <span style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $payroll->employee?->nip ?? '-' }}</span>
                            </td>
                            <td style="padding:9px 14px;text-align:right;white-space:nowrap;">{{ $base !== null ? 'Rp'.number_format($base, 0, ',', '.') : '-' }}</td>
                            <td style="padding:9px 14px;text-align:right;white-space:nowrap;">{{ $percent !== null ? rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',').'%' : '-' }}</td>
                            <td style="padding:9px 14px;text-align:right;white-space:nowrap;font-weight:700;">Rp{{ number_format((float) ($gajiPokokItem?->amount ?? 0), 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding:24px;text-align:center;color:var(--text-faint);">
                                {{ $periodId ? 'Belum ada payroll di periode ini.' : 'Pilih periode gaji dulu.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
