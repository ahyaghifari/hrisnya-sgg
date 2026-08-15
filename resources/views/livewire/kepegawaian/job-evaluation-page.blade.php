<div>
    <style>
        .je-tab { padding: 10px 4px; margin-right: 22px; font-size: 13px; font-weight: 600; color: var(--text-faint); background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; font-family: inherit; }
        .je-tab.is-active { color: #042c6c; border-bottom-color: #042c6c; }
        html.dark .je-tab.is-active { color: #6ea8e0; border-bottom-color: #6ea8e0; }
    </style>

    <x-page-header title="Job Evaluation" subtitle="Skor JE per jabatan — dipakai sebagai pembagi rasio Jasa Pelayanan (rumah sakit)." />

    <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;justify-content:space-between;">
        @if (auth()->user()->canFilterByCompany())
            <div style="min-width:220px;">
                <label class="form-label">Perusahaan</label>
                <select wire:model.live="companyId" class="form-input">
                    @foreach ($this->companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <div style="border-bottom:1.5px solid var(--border);margin-bottom:16px;">
        <button type="button" wire:click="setTab('saat-ini')" class="je-tab {{ $tab === 'saat-ini' ? 'is-active' : '' }}">Saat Ini</button>
        <button type="button" wire:click="setTab('riwayat')" class="je-tab {{ $tab === 'riwayat' ? 'is-active' : '' }}">Riwayat</button>
    </div>

    @if ($tab === 'saat-ini')
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:16px;">
            @if ($this->jobPositions->isEmpty())
                <p style="font-size:11.5px;color:var(--text-faint);">Belum ada Jabatan (status Approved) untuk perusahaan ini.</p>
            @else
                @php $no = 0; @endphp
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:11.5px;">
                        <thead>
                            <tr style="border-bottom:1.5px solid var(--border);">
                                <th style="text-align:left;padding:6px 8px;width:36px;">No</th>
                                <th style="text-align:left;padding:6px 8px;">Nama Jabatan</th>
                                <th style="text-align:left;padding:6px 8px;width:140px;">JE Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->groupedJobPositions as $pangkatName => $positions)
                                <tr>
                                    <td colspan="3" style="padding:12px 8px 4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.03em;color:var(--text-faint);border-bottom:1px solid var(--border);">
                                        {{ $pangkatName }} <span style="font-weight:500;text-transform:none;letter-spacing:normal;">({{ $positions->count() }} jabatan)</span>
                                    </td>
                                </tr>
                                @foreach ($positions as $p)
                                    @php $no++; @endphp
                                    <tr style="border-bottom:1px solid var(--border-soft);">
                                        <td style="padding:5px 8px;color:var(--text-faint);">{{ $no }}</td>
                                        <td style="padding:5px 8px;font-weight:600;">{{ $p->name }}</td>
                                        <td style="padding:5px 8px;">
                                            <input type="number" class="form-input" style="font-size:11.5px;padding:5px 8px;" wire:model="jeValues.{{ $p->id }}" @disabled(! $this->canManage)>
                                            @error("jeValues.{$p->id}") <p style="font-size:10.5px;color:#dc2626;margin:2px 0 0;">{{ $message }}</p> @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding-top:12px;border-top:1px dashed var(--border-soft);">
                    <span style="font-size:12px;font-weight:700;">Total JE: {{ number_format($this->totalJe) }}</span>
                    @if ($this->canManage)
                        <button type="button" wire:click="saveJe" wire:loading.attr="disabled" wire:target="saveJe" class="btn-primary" style="padding:9px 20px;font-size:12.5px;">Simpan</button>
                    @endif
                </div>
            @endif
        </div>
    @else
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
            @forelse ($this->history as $log)
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 18px;border-bottom:1px solid var(--border-soft);">
                    <div>
                        <span style="font-size:12.5px;font-weight:600;color:var(--text-heading);">{{ $log->label }}</span>
                        <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">
                            {{ $log->old_value ?? '-' }} <i class="fa-solid fa-arrow-right" style="font-size:9px;"></i> {{ $log->new_value ?? '-' }}
                        </p>
                    </div>
                    <div style="text-align:right;font-size:11px;color:var(--text-faint);">
                        <div>{{ $log->user?->name ?? 'Sistem' }}</div>
                        <div>{{ $log->created_at->translatedFormat('d M Y, H:i') }}</div>
                    </div>
                </div>
            @empty
                <div style="padding:32px;text-align:center;">
                    <i class="fa-solid fa-clock-rotate-left" style="font-size:22px;color:var(--text-faint);"></i>
                    <p style="font-size:12.5px;color:var(--text-muted);margin:12px 0 0;">Belum ada riwayat perubahan JE untuk perusahaan ini.</p>
                </div>
            @endforelse
        </div>
    @endif
</div>
