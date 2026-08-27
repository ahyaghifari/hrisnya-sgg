<div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div>
                <label class="form-label">Periode</label>
                <input type="month" wire:model.live="period" class="form-input" style="max-width:180px;">
            </div>
            <span style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin-top:16px;">{{ $this->periodLabel }}</span>
            <button type="button" wire:click="exportExcel" class="btn-success" style="font-size:11px;padding:7px 12px;white-space:nowrap;margin-top:16px;">
                <i class="fa-solid fa-file-excel"></i> Export Excel
            </button>
            @if ($this->canEditJadwal)
                <div style="margin-top:16px;">
                    <label class="btn-info" style="font-size:11px;padding:7px 12px;white-space:nowrap;cursor:pointer;margin:0;">
                        <i class="fa-solid fa-file-import"></i> Import Excel
                        <input type="file" wire:model="importFile" accept=".xlsx,.xls" style="display:none;">
                    </label>
                    <div wire:loading wire:target="importFile" style="font-size:10.5px;color:var(--text-muted);margin-top:2px;">Mengimpor...</div>
                    @error('importFile') <div style="font-size:10.5px;color:var(--danger-text);margin-top:2px;">{{ $message }}</div> @enderror
                </div>
            @endif
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
                @if ($this->canEditJadwal)
                    <button type="button" wire:click="generateFromTemplate" wire:confirm="Generate roster {{ $this->periodLabel }} dari template, mulai hari ke-{{ $rotationStartDay }} pada tanggal yang dipilih? Tanggal yang sudah punya roster (manual/koreksi) TIDAK akan ditimpa." class="btn-primary" style="font-size:11px;padding:7px 12px;white-space:nowrap;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Generate
                    </button>
                @endif
            @endif

            {{-- Edit manual per sel — kebuka buat jadwal Template maupun manual. Sel dikunci
            sampai "Mulai Isi Jadwal" diklik, perubahan ditampung dulu, baru ditulis ke DB pas
            "Simpan". --}}
            @if ($this->canEditJadwal)
                @if (! $editMode)
                    <button type="button" wire:click="startEdit" class="btn-primary" style="font-size:11px;padding:7px 12px;white-space:nowrap;">
                        <i class="fa-solid fa-pen"></i> Isi Jadwal
                    </button>
                @else
                    <button type="button" wire:click="cancelEdit" class="btn-ghost" style="font-size:11px;padding:7px 12px;white-space:nowrap;">
                        Batal
                    </button>
                    <button type="button" wire:click="saveRoster" class="btn-primary" style="font-size:11px;padding:7px 12px;white-space:nowrap;">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                    </button>
                @endif
            @endif
        </div>
    </div>

    {{-- Keterangan warna kode shift — sama kayak tab Template Shift, biar konsisten. --}}
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:10px;font-size:13px;color:var(--text-muted);">
        <span style="font-weight:700;color:var(--text-heading);">Keterangan:</span>
        @foreach ($schedule->shiftTypes as $shiftType)
            <span style="display:inline-flex;align-items:center;gap:5px;">
                <span style="width:11px;height:11px;border-radius:50%;background:{{ $shiftType->color ?: 'var(--text-faint)' }};flex-shrink:0;"></span>
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
                        <td style="padding:6px 10px;white-space:nowrap;font-weight:600;position:sticky;left:0;background:var(--surface);z-index:1;">{{ $employee->name }}</td>
                        @foreach ($this->dates as $date)
                            @php
                                // Kosong (belum ada baris ScheduleShiftAssignment sama sekali) = benar-benar
                                // kosong, BUKAN Libur — tampilan netral, tidak ditulis ke DB. "off" = eksplisit
                                // disimpan sebagai off_day=true (baru dapat huruf "L" + warna merah), konsisten
                                // dengan rotasi di tab Template Shift yang memang nyimpen off_day.
                                // editMode aktif -> baca/tulis dari $draft (belum tersimpan). Selain itu (locked
                                // atau belum mulai edit) -> baca dari roster tersimpan, cuma buat ditampilkan.
                                $current = $editMode
                                    ? ($draft[$employee->id][$date->toDateString()] ?? '')
                                    : ($userRoster[$date->toDateString()] ?? '');
                                $isOff = $current === 'off';
                                $currentType = $schedule->shiftTypes->firstWhere('id', $current);
                                $currentColor = $currentType?->color;
                                $currentFontColor = $isOff ? '#7f1d1d' : ($currentType?->font_color ?: 'var(--text-body)');
                                $locked = ! $editMode;
                            @endphp
                            <td style="padding:2px;text-align:center;">
                                <select
                                    @if ($editMode)
                                        wire:model="draft.{{ $employee->id }}.{{ $date->toDateString() }}"
                                    @endif
                                    @disabled($locked)
                                    @if (! $editMode)
                                        title="Klik &quot;Mulai Isi Jadwal&quot; dulu buat mengubah."
                                    @endif
                                    style="width:48px;padding:5px 1px;font-size:9.5px;text-align:center;font-weight:700;border:none;border-radius:5px;{{ $locked ? 'cursor:not-allowed;opacity:0.7;' : 'cursor:pointer;' }}color:{{ $currentFontColor }};{{ $isOff ? 'background:color-mix(in srgb, #dc2626 55%, white);' : ($currentColor ? "background:{$currentColor};" : 'background:var(--surface-alt);') }}">
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

    <div style="margin-top:12px;display:flex;gap:8px;">
        <button type="button" wire:click="toggleLogs" class="btn-ghost" style="font-size:11px;padding:7px 12px;white-space:nowrap;">
            <i class="fa-solid fa-clock-rotate-left"></i> {{ $showLogs ? 'Sembunyikan' : 'Lihat' }} Riwayat Perubahan
        </button>
        @if ($this->canEditJadwal)
            <button type="button" wire:click="resetPeriod" wire:confirm="Hapus SEMUA roster {{ $this->periodLabel }} (termasuk yang sudah dikoreksi manual)? Tindakan ini tidak bisa dibatalkan." class="btn-ghost" style="font-size:11px;padding:7px 12px;white-space:nowrap;color:var(--danger-text);">
                <i class="fa-solid fa-rotate-left"></i> Reset Jadwal
            </button>
        @endif
    </div>

    @if ($showLogs)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:auto;max-height:400px;margin-top:10px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Keterangan</th>
                        <th>Diubah Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rosterLogs as $log)
                        <tr>
                            <td style="white-space:nowrap;">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td>{{ $this->describeRosterLog($log) }}</td>
                            <td>{{ $log->changedBy?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="padding:20px;text-align:center;color:var(--text-faint);">Belum ada riwayat perubahan di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
