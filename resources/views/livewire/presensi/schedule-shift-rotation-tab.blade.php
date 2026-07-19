<div>
    @unless ($schedule->use_template)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
            <i class="fa-solid fa-repeat" style="font-size:22px;color:var(--text-faint);"></i>
            <p style="font-size:12.5px;color:var(--text-muted);margin:12px 0 4px;">Jadwal ini belum memakai Template Rotasi.</p>
            <p style="font-size:11px;color:var(--text-faint);margin:0 0 16px;">Kalau diaktifkan, roster bisa digenerate otomatis per periode dari pola rotasi di bawah. Kalau tidak, roster diisi manual satu-satu di tab "Detail Penjadwalan".</p>
            <button type="button" wire:click="activateTemplate" class="btn-primary">
                <i class="fa-solid fa-power-off"></i> Aktifkan Template Rotasi
            </button>
        </div>
    @else
        @if ($this->employees->isEmpty())
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:24px;text-align:center;color:var(--text-faint);font-size:12.5px;">
                Belum ada karyawan di jadwal ini — pilih karyawan dulu lewat Edit Jadwal.
            </div>
        @else
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;">
                <div style="display:inline-flex;gap:6px;">
                    <button type="button" wire:click="addDayColumn" class="btn-ghost" style="font-size:11.5px;padding:7px 14px;">
                        <i class="fa-solid fa-plus"></i> Tambah Hari
                    </button>
                    <button type="button" wire:click="removeDayColumn" class="btn-ghost" style="font-size:11.5px;padding:7px 14px;" {{ $dayCount <= 1 ? 'disabled' : '' }}>
                        <i class="fa-solid fa-minus"></i> Kurangi Hari
                    </button>
                </div>
                <button type="button" wire:click="deactivateTemplate" wire:confirm="Batalkan pemakaian Template Rotasi untuk jadwal ini? Roster yang sudah tergenerate TIDAK ikut terhapus, cuma cara isi rosternya jadi manual lagi." class="btn-ghost" style="font-size:11.5px;padding:7px 14px;color:#dc2626;">
                    <i class="fa-solid fa-xmark"></i> Batalkan Penggunaan Template
                </button>
            </div>

            {{-- Keterangan inisial + warna kode shift, biar tabelnya tidak perlu diperlebar nampilin nama penuh. --}}
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:10px;font-size:10.5px;color:var(--text-muted);">
                <span style="font-weight:700;color:var(--text-heading);">Keterangan:</span>
                @foreach ($schedule->shiftTypes as $shiftType)
                    <span style="display:inline-flex;align-items:center;gap:5px;">
                        <span style="width:9px;height:9px;border-radius:50%;background:{{ $shiftType->color ?: 'var(--text-faint)' }};flex-shrink:0;"></span>
                        <strong>{{ $shiftType->code }}</strong> = {{ $shiftType->name }}
                    </span>
                @endforeach
                <span style="display:inline-flex;align-items:center;gap:5px;">
                    <span style="width:9px;height:9px;border-radius:50%;background:var(--text-faint);flex-shrink:0;"></span>
                    <strong>Libur</strong> = tidak kerja
                </span>
            </div>

            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:auto;">
                <table style="border-collapse:collapse;font-size:11.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:8px 10px;white-space:nowrap;position:sticky;left:0;background:var(--surface);z-index:1;">Karyawan</th>
                            @for ($d = 1; $d <= $dayCount; $d++)
                                <th style="text-align:center;padding:8px 10px;white-space:nowrap;min-width:100px;">Hari {{ $d }}</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->employees as $employee)
                            <tr wire:key="'rot-row-{{ $employee->id }}'" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:6px 10px;white-space:nowrap;font-weight:600;position:sticky;left:0;background:var(--surface);">{{ $employee->name }}</td>
                                @for ($d = 1; $d <= $dayCount; $d++)
                                    @php
                                        $selectedType = $schedule->shiftTypes->firstWhere('id', $value = $rows[$employee->id][$d] ?? '');
                                        $cellColor = $value === 'off' ? 'color-mix(in srgb, #dc2626 55%, white)' : $selectedType?->color;
                                        $cellFontColor = $value === 'off' ? '#7f1d1d' : ($selectedType?->font_color ?: 'var(--text-body)');
                                    @endphp
                                    <td style="padding:0;">
                                        {{-- Warna solid persis sesuai isian form (sama pola tabel tab Jam Kerja) —
                                             tidak dicampur/opacity-kan, tidak ikut tema gelap/terang. --}}
                                        <select wire:model.live="rows.{{ $employee->id }}.{{ $d }}"
                                            style="width:100%;height:100%;min-height:38px;padding:6px 8px;font-size:11px;border:none;border-radius:0;text-align:center;font-weight:700;cursor:pointer;color:{{ $cellFontColor }};{{ $cellColor ? "background:{$cellColor};" : 'background:var(--surface-alt);' }}">
                                            <option value="">-- Pilih --</option>
                                            @foreach ($schedule->shiftTypes as $shiftType)
                                                <option value="{{ $shiftType->id }}">{{ $shiftType->code }}</option>
                                            @endforeach
                                            <option value="off">Libur</option>
                                        </select>
                                    </td>
                                @endfor
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                <button type="button" wire:click="saveRotation" class="btn-primary">Simpan Template Rotasi</button>
            </div>
        @endif
    @endunless
</div>
