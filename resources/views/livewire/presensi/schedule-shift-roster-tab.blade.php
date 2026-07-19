<div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div>
                <label class="form-label">Periode</label>
                <input type="month" wire:model.live="period" class="form-input" style="max-width:180px;">
            </div>
            <span style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin-top:16px;">{{ $this->periodLabel }}</span>
            <button type="button" wire:click="exportExcel" class="btn-ghost" style="font-size:11px;padding:7px 12px;white-space:nowrap;margin-top:16px;">
                <i class="fa-solid fa-file-excel"></i> Export Excel
            </button>
        </div>

        <div style="display:flex;align-items:flex-end;gap:6px;">
            @if ($schedule->use_template)
                <div>
                    <label class="form-label" style="font-size:10px;margin-bottom:3px;">Mulai Dari</label>
                    <input type="date" wire:model="rotationStartDate" class="form-input" style="width:128px;padding:6px 8px;font-size:11px;">
                    @error('rotationStartDate') <p style="font-size:9.5px;color:#dc2626;margin-top:2px;">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label" style="font-size:10px;margin-bottom:3px;">Hari Ke-</label>
                    <select wire:model="rotationStartDay" class="form-input" style="width:78px;padding:6px 8px;font-size:11px;">
                        @for ($d = 1; $d <= $this->cycleLength; $d++)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endfor
                    </select>
                    @error('rotationStartDay') <p style="font-size:9.5px;color:#dc2626;margin-top:2px;">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="generateFromTemplate" wire:confirm="Generate roster {{ $this->periodLabel }} dari template, mulai hari ke-{{ $rotationStartDay }} pada tanggal yang dipilih? Tanggal yang sudah punya roster (manual/koreksi) TIDAK akan ditimpa." class="btn-primary" style="font-size:11px;padding:7px 12px;white-space:nowrap;">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Generate
                </button>
            @endif
        </div>
    </div>

    {{-- Keterangan warna kode shift — sama kayak tab Template Shift, biar konsisten. --}}
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:10px;font-size:10.5px;color:var(--text-muted);">
        <span style="font-weight:700;color:var(--text-heading);">Keterangan:</span>
        @foreach ($schedule->shiftTypes as $shiftType)
            <span style="display:inline-flex;align-items:center;gap:5px;">
                <span style="width:9px;height:9px;border-radius:50%;background:{{ $shiftType->color ?: 'var(--text-faint)' }};flex-shrink:0;"></span>
                <strong>{{ $shiftType->code }}</strong> = {{ $shiftType->name }}
            </span>
        @endforeach
    </div>

    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:auto;max-height:600px;">
        <table style="border-collapse:collapse;font-size:10.5px;">
            <thead>
                <tr style="border-bottom:1.5px solid var(--border);">
                    <th style="text-align:left;padding:8px 10px;white-space:nowrap;position:sticky;left:0;top:0;background:var(--surface);z-index:2;">Karyawan</th>
                    @foreach ($this->dates as $date)
                        <th style="text-align:center;padding:8px 4px;white-space:nowrap;min-width:52px;position:sticky;top:0;background:var(--surface);{{ $date->isToday() ? 'color:#0284c7;' : '' }}">
                            {{ $date->format('d') }}<br>
                            <span style="font-weight:400;color:var(--text-faint);">{{ $date->translatedFormat('D') }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($this->employees as $employee)
                    @php $userRoster = $this->roster[$employee->id] ?? []; @endphp
                    <tr wire:key="'roster-{{ $employee->id }}'" style="border-bottom:1px solid var(--border-soft);">
                        <td style="padding:6px 10px;white-space:nowrap;font-weight:600;position:sticky;left:0;background:var(--surface);">{{ $employee->name }}</td>
                        @foreach ($this->dates as $date)
                            @php
                                // Kosong (belum ada baris ScheduleShiftAssignment sama sekali) = benar-benar
                                // kosong, BUKAN Libur — tampilan netral, tidak ditulis ke DB. "off" = eksplisit
                                // disimpan sebagai off_day=true (baru dapat huruf "L" + warna merah), konsisten
                                // dengan rotasi di tab Template Shift yang memang nyimpen off_day.
                                $current = $userRoster[$date->toDateString()] ?? '';
                                $isOff = $current === 'off';
                                $currentType = $schedule->shiftTypes->firstWhere('id', $current);
                                $currentColor = $currentType?->color;
                                $currentFontColor = $isOff ? '#7f1d1d' : ($currentType?->font_color ?: 'var(--text-body)');
                            @endphp
                            <td style="padding:2px;text-align:center;">
                                <select
                                    wire:change="setCell('{{ $employee->id }}', '{{ $date->toDateString() }}', $event.target.value)"
                                    style="width:48px;padding:5px 1px;font-size:9.5px;text-align:center;font-weight:700;border:none;border-radius:5px;cursor:pointer;color:{{ $currentFontColor }};{{ $isOff ? 'background:color-mix(in srgb, #dc2626 55%, white);' : ($currentColor ? "background:{$currentColor};" : 'background:var(--surface-alt);') }}">
                                    <option value="" {{ $current === '' ? 'selected' : '' }}>-</option>
                                    @foreach ($schedule->shiftTypes as $shiftType)
                                        <option value="{{ $shiftType->id }}" {{ $current === $shiftType->id ? 'selected' : '' }}>{{ $shiftType->code }}</option>
                                    @endforeach
                                    <option value="off" {{ $isOff ? 'selected' : '' }}>L</option>
                                </select>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="32" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada karyawan di jadwal ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
