@php
    // Oncall TIDAK boleh dipilih manual di roster - cuma bisa didapat lewat Extra Shift pas
    // karyawan libur (lihat ShiftType::ONCALL docblock). Dropdown pengisian roster cuma
    // Pagi/Siang/Malam/Middle; baris Oncall tetap ada di $schedule->shiftTypes (dipakai buat
    // nampilin warna/kode kalau ada hari yang sudah ketiban Oncall dari Extra Shift).
    $assignableShiftTypes = $schedule->shiftTypes->where('shift_type', '!=', \App\Enum\ShiftType::ONCALL->value);

    // Peta warna per nilai sel buat Alpine — biar drag/undo bisa langsung ganti background sel
    // tanpa nunggu re-render Livewire (wire:model deferred).
    $shiftStyleMap = $schedule->shiftTypes->mapWithKeys(fn ($t) => [
        (string) $t->id => ['bg' => $t->color ?: 'var(--surface-alt)', 'fg' => $t->font_color ?: 'var(--text-body)'],
    ])->all();
    $shiftStyleMap['off'] = ['bg' => 'color-mix(in srgb, #dc2626 55%, white)', 'fg' => '#7f1d1d'];
    $shiftStyleMap[''] = ['bg' => 'var(--surface-alt)', 'fg' => 'var(--text-body)'];
@endphp
<div x-data="rosterFill(@js($shiftStyleMap))"
    x-effect="if (! $wire.editMode) reset(); if (lastPeriod !== $wire.period) { lastPeriod = $wire.period; history = []; clearSel(); $nextTick(() => scrollToDay1()) }"
    @mouseup.window="onUp()" @mousemove.window="onMove($event)"
    @keydown.window="if (fillMode && ($event.ctrlKey || $event.metaKey) && ($event.key || '').toLowerCase() === 'z') { $event.preventDefault(); undo() }">
    {{-- Isi Cepat ala Excel: pas editMode, nyalakan tombol "Isi Cepat" -> select tiap sel jadi
    non-klik, sel dipilih dengan seret mendatar (1 baris karyawan), lalu handle kecil di pojok
    kanan-bawah diseret ke kanan buat mengulang pola sel terpilih (mis. P P P L -> P P P L P P P L).
    Full frontend: nilai baru ditulis ke <select> + dispatch 'input' biar Livewire nyerap ke $draft,
    disimpan tetap lewat tombol "Simpan" (saveRoster) yang sudah ada. --}}
    <style>
        .rf-sel { outline:2px solid #042c6c; outline-offset:-2px; }
        .rf-preview { outline:2px dashed #0284c7; outline-offset:-2px; background:rgba(2,132,199,0.10); }
        .rf-on select { pointer-events:none; }
        .rf-on .rf-cell { cursor:cell; }
        .rf-on select { opacity:0.92; }
        .rf-grid-on { box-shadow:0 0 0 2px #042c6c inset, var(--shadow-card) !important; }
        .rf-handle { position:absolute; right:-2px; bottom:-2px; width:9px; height:9px; background:#042c6c; border:1px solid #fff; cursor:crosshair; z-index:4; }
        .rf-hint { display:flex;align-items:center;gap:6px;font-size:10.5px;font-weight:600;color:#042c6c;background:color-mix(in srgb,#042c6c 8%,white);border:1px solid color-mix(in srgb,#042c6c 25%,white);border-radius:8px;padding:5px 10px;margin-bottom:8px; }
        /* Riwayat Tukar Shift tetap ke-mount (dipakai listener sel terkunci), cuma ikon pemicunya disembunyikan biar toolbar bersih. */
        .rf-hide-swaptrigger > div > button { display:none; }
    </style>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:10px;">
            <div>
                <label class="form-label">Periode</label>
                <input type="month" wire:model.live="period" min="2026-09" class="form-input" style="max-width:180px;">
            </div>
            <span class="rf-hide-swaptrigger" style="margin-top:16px;">
                <livewire:presensi.schedule-swap-history-sheet :scheduleId="$schedule->id" :key="'roster-swap-history-'.$schedule->id" />
            </span>
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
            {{-- Generate cuma relevan buat mengisi dari template - disembunyikan pas mode Isi
                 Jadwal (editMode) aktif biar tidak ambigu generate nimpa draft manual yang lagi
                 dikerjakan. --}}
            @if ($schedule->use_template && $this->canEditJadwal && ! $editMode)
                {{-- "Mulai Dari" + "Hari Ke-" dipindah ke popover tombol Generate biar toolbar nggak penuh. --}}
                <div style="position:relative;" x-data="{ genOpen: false }" @click.outside="genOpen = false" @keydown.escape="genOpen = false">
                    <button type="button" @click="genOpen = ! genOpen" class="btn-primary" style="font-size:11px;padding:7px 12px;white-space:nowrap;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Generate <i class="fa-solid fa-chevron-down" style="font-size:8px;margin-left:2px;"></i>
                    </button>
                    <div x-show="genOpen" x-cloak x-transition
                        style="position:absolute;right:0;top:calc(100% + 6px);z-index:20;background:var(--surface);border:1px solid var(--border);border-radius:10px;box-shadow:var(--shadow-card);padding:12px;width:230px;display:flex;flex-direction:column;gap:8px;">
                        <div>
                            <label class="form-label" style="font-size:10px;margin-bottom:3px;">Mulai Dari</label>
                            <input type="date" wire:model="rotationStartDate" class="form-input" style="width:100%;padding:6px 8px;font-size:11px;">
                            @error('rotationStartDate') <p style="font-size:9.5px;color:#dc2626;margin-top:2px;">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label" style="font-size:10px;margin-bottom:3px;">Hari Ke-</label>
                            <select wire:model="rotationStartDay" class="form-input" style="width:100%;padding:6px 8px;font-size:11px;">
                                @for ($d = 1; $d <= $this->cycleLength; $d++)
                                    <option value="{{ $d }}">{{ $d }}</option>
                                @endfor
                            </select>
                            @error('rotationStartDay') <p style="font-size:9.5px;color:#dc2626;margin-top:2px;">{{ $message }}</p> @enderror
                        </div>
                        <button type="button" wire:click="generateFromTemplate" @click="genOpen = false"
                            wire:confirm="Generate roster {{ $this->periodLabel }} dari template, mulai hari ke-{{ $rotationStartDay }} pada tanggal yang dipilih? Tanggal yang sudah punya roster (manual/koreksi) TIDAK akan ditimpa."
                            class="btn-primary" style="font-size:11px;padding:7px 12px;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Jalankan Generate
                        </button>
                    </div>
                </div>
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
                    <button type="button" @click="fillMode = ! fillMode; if (! fillMode) clearSel()"
                        :class="fillMode ? 'btn-primary' : 'btn-ghost'"
                        title="Seret mendatar buat pilih sel, lalu seret handle di pojok kanan-bawah buat mengulang pola (mis. P P P L)."
                        style="font-size:11px;padding:7px 12px;white-space:nowrap;">
                        <i class="fa-solid fa-table-cells"></i> Isi Cepat
                    </button>
                    <button type="button" x-show="fillMode" x-cloak @click="undo()" :disabled="! history.length"
                        title="Batalkan isi-pola terakhir (Ctrl+Z)"
                        class="btn-ghost" style="font-size:11px;padding:7px 12px;white-space:nowrap;"
                        :style="history.length ? '' : 'opacity:0.45;cursor:not-allowed;'">
                        <i class="fa-solid fa-rotate-left"></i> Undo<span x-show="history.length" x-text="' (' + history.length + ')'"></span>
                    </button>
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
            <strong style="font-size:12.5px;font-weight:800;padding:3px 10px;border-radius:6px;color:{{ $shiftType->font_color ?: '#fff' }};background:{{ $shiftType->color ?: 'var(--text-faint)' }};">{{ $shiftType->code }} ({{ mb_strtoupper($shiftType->name) }})</strong>
        @endforeach
    </div>

    <div x-show="fillMode" class="rf-hint" x-cloak>
        <i class="fa-solid fa-table-cells"></i>
        <span>Mode <strong>Isi Cepat</strong> aktif — dropdown sel dimatikan. Seret mendatar buat pilih sel, lalu seret kotak biru di pojok kanan-bawah buat mengulang pola. Matikan tombol "Isi Cepat" buat edit sel satu-satu lagi.</span>
    </div>
    <div data-scrollbox :class="{ 'rf-on': fillMode, 'rf-grid-on': fillMode }" style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
        <table style="border-collapse:collapse;font-size:11.5px;">
            <thead>
                <tr style="border-bottom:1.5px solid var(--border);">
                    <th class="rf-sticky-col" style="text-align:left;padding:10px 14px;white-space:nowrap;position:sticky;left:0;top:0;background:var(--surface);z-index:4;">Karyawan</th>
                    @foreach ($this->leadDates as $ld)
                        <th title="Bulan sebelumnya — hanya konteks, tidak bisa diedit" style="text-align:center;padding:8px 5px;white-space:nowrap;min-width:56px;position:sticky;top:0;background:var(--surface-alt);z-index:3;color:var(--text-faint);">
                            {{ $ld->format('d') }}<br>
                            <span style="font-weight:400;">{{ $ld->translatedFormat('D') }}</span>
                        </th>
                    @endforeach
                    @foreach ($this->dates as $date)
                        <th @if ($loop->first) data-day1 @endif style="text-align:center;padding:8px 5px;white-space:nowrap;min-width:56px;position:sticky;top:0;background:var(--surface);z-index:3;{{ $loop->first ? 'border-left:2px solid var(--border);' : '' }}{{ $date->isToday() ? 'color:#0284c7;' : '' }}">
                            {{ $date->format('d') }}<br>
                            <span style="font-weight:400;color:var(--text-faint);">{{ $date->translatedFormat('D') }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    // Baris pemisah nama tim — colspan penuh, disisipkan tiap kali tim-nya
                    // beda dari baris sebelumnya (grid sudah diurut per tim di
                    // getEmployeesProperty(), jadi cukup bandingkan grup berurutan). Murni visual,
                    // TIDAK dapat rfRow/rfC - tidak kesentuh sama sekali sama mekanisme drag-select
                    // (data-r/data-c dibaca by-attribute, bukan posisi DOM, lihat rosterFill()).
                    // Sengaja BARIS, bukan kolom terpisah (instruksi user 2026-09-12) - biar tiap
                    // tim kelihatan sebagai blok terpisah, tidak numpuk jadi 1 kolom sempit yang
                    // bikin susah bandingkan anggota tim yang beda.
                    $totalCols = 1 + count($this->leadDates) + count($this->dates);
                    $lastTeamGroupKey = null;
                @endphp
                @forelse ($this->employees as $employee)
                    @php
                        $userRoster = $this->roster[$employee->id] ?? [];
                        $leadCount = count($this->leadDates);
                        $rfRow = $loop->index;
                        $teamInfo = $this->employeeTeamInfo[$employee->id] ?? null;
                        $teamColor = $teamInfo['color'] ?? null;
                        $teamGroupKey = $teamInfo['name'] ?? '__tanpa_tim__';
                        $isNewTeamGroup = $teamGroupKey !== $lastTeamGroupKey;
                        $lastTeamGroupKey = $teamGroupKey;
                        // null (Tanpa Tim / tim belum dibatasi) -> semua shift schedule ini boleh,
                        // lihat ScheduleShiftTeamMember::allowedShiftTypeIdsFor().
                        $allowedShiftIds = $teamInfo['shift_type_ids'] ?? null;
                        $rowShiftTypes = $allowedShiftIds ? $assignableShiftTypes->whereIn('id', $allowedShiftIds) : $assignableShiftTypes;
                        // Kode + nama lengkap shift jatah tim ini ditempel di baris pemisah - biar
                        // kelihatan tanpa scroll/buka tab Tim (instruksi user 2026-09-14). Cuma
                        // ditampilkan kalau BENERAN dibatasi (null = semua shift boleh, tidak perlu
                        // disebutin satu-satu).
                        $groupShiftCodes = $allowedShiftIds
                            ? $rowShiftTypes->map(fn ($t) => "{$t->code} ({$t->name})")->implode(', ')
                            : null;
                    @endphp
                    @if ($schedule->use_teams && $isNewTeamGroup)
                        <tr wire:key="'roster-group-{{ $teamGroupKey }}-{{ $employee->id }}'">
                            {{-- Baris pemisah tim ini yang dikasih warna (bukan baris rosternya) —
                                 background = warna tim langsung, teks putih biar kebaca. --}}
                            <td colspan="{{ $totalCols }}" style="padding:7px 14px;text-align:center;font-size:12px;font-weight:800;color:{{ $teamColor ? '#fff' : 'var(--text-heading)' }};background:{{ $teamColor ?? 'var(--surface-alt)' }};border-bottom:1px solid var(--border-soft);">
                                {{ $teamInfo['name'] ?? 'Tanpa Tim' }}
                                @if ($groupShiftCodes)
                                    <span style="font-weight:600;font-size:10.5px;opacity:0.85;">&nbsp;&middot; {{ $groupShiftCodes }}</span>
                                @endif
                            </td>
                        </tr>
                    @endif
                    <tr wire:key="'roster-{{ $employee->id }}'" style="border-bottom:1px solid var(--border-soft);">
                        <td style="padding:6px 12px;white-space:nowrap;font-weight:600;position:sticky;left:0;background:var(--surface);z-index:1;">{{ $employee->name }}</td>
                        @foreach ($this->leadDates as $ld)
                            @php
                                $lval = $userRoster[$ld->toDateString()] ?? '';
                                $lIsOff = $lval === 'off';
                                $lType = $schedule->shiftTypes->firstWhere('id', $lval);
                                $lColor = $lType?->color;
                                $lFont = $lIsOff ? '#7f1d1d' : ($lType?->font_color ?: 'var(--text-faint)');
                                $rfC = $loop->index - $leadCount; // -7..-1
                            @endphp
                            <td class="rf-cell" data-r="{{ $rfRow }}" data-c="{{ $rfC }}" data-locked="0" data-lead="1"
                                @mousedown="cellDown($event, {{ $rfRow }}, {{ $rfC }})" @mouseenter="cellEnter({{ $rfRow }}, {{ $rfC }})"
                                :class="cellClass({{ $rfRow }}, {{ $rfC }})"
                                title="Bulan sebelumnya — konteks buat nyambung pola, tidak bisa diedit"
                                style="padding:2px;text-align:center;position:relative;">
                                <select disabled
                                    style="width:52px;padding:6px 2px;font-size:10.5px;text-align:center;font-weight:700;border:none;border-radius:5px;opacity:0.5;cursor:not-allowed;color:{{ $lFont }};{{ $lIsOff ? 'background:color-mix(in srgb, #dc2626 55%, white);' : ($lColor ? "background:{$lColor};" : 'background:var(--surface-alt);') }}">
                                    <option value="" {{ $lval === '' ? 'selected' : '' }}>-</option>
                                    @foreach ($assignableShiftTypes as $shiftType)
                                        <option value="{{ $shiftType->id }}" {{ $lval === $shiftType->id ? 'selected' : '' }}>{{ $shiftType->code }}</option>
                                    @endforeach
                                    <option value="off" {{ $lIsOff ? 'selected' : '' }}>L</option>
                                </select>
                            </td>
                        @endforeach
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
                                // Sel yang jadwalnya lagi/sudah dipakai pengajuan tukar shift -> dikunci
                                // total, tidak bisa diedit dari halaman jadwal (biar tidak bentrok sama
                                // eksekusi swap). Lihat ScheduleShiftRosterTab::getSwapCellsProperty().
                                $swapLocked = isset($this->swapCells[$employee->id.'|'.$date->toDateString()]);
                                // Yang cuma punya Update:ShiftScheduleAssignments (bukan Edit Jadwal)
                                // dibatasi mulai hari ini - tanggal yang sudah lewat dikunci di grid juga.
                                $pastLocked = ! $this->canEditPastDates && $date->lt(today());
                                $locked = ! $editMode || $swapLocked || $pastLocked;
                                $rfR = $loop->parent->index;
                                $rfC = $loop->index;
                            @endphp
                            <td class="rf-cell" data-r="{{ $rfR }}" data-c="{{ $rfC }}" data-locked="{{ ($swapLocked || $pastLocked) ? '1' : '0' }}"
                                @mousedown="cellDown($event, {{ $rfR }}, {{ $rfC }})" @mouseenter="cellEnter({{ $rfR }}, {{ $rfC }})"
                                :class="cellClass({{ $rfR }}, {{ $rfC }})"
                                style="padding:2px;text-align:center;position:relative;">
                                <template x-if="fillMode && sel && ! selecting && sel.r === {{ $rfR }} && sel.hi === {{ $rfC }}">
                                    <div class="rf-handle" @mousedown.stop.prevent="handleDown()"></div>
                                </template>
                                @if ($swapLocked)
                                    <button type="button" wire:click="$dispatch('open-schedule-swap-history')" title="Jadwal ini terkait pengajuan tukar shift — tidak bisa diubah dari sini. Klik untuk lihat riwayat."
                                        style="position:absolute;top:0;right:0;z-index:3;background:none;border:none;padding:1px 2px;cursor:pointer;color:#b45309;font-size:8.5px;line-height:1;">
                                        <i class="fa-solid fa-right-left"></i>
                                    </button>
                                @endif
                                <select
                                    {{-- wire:key ikut nilainya, bukan cuma posisi - morphdom kadang cuma
                                    patch atribut "selected" tanpa nyamain properti .value/tampilan visual
                                    <select> yang sudah kepegang browser, jadi abis Simpan warna berubah
                                    tapi teksnya masih nilai lama sampai refresh manual. wire:key beda ->
                                    dianggap elemen baru -> browser bikin ulang dari nol, bukan di-patch. --}}
                                    wire:key="'cell-{{ $employee->id }}-{{ $date->toDateString() }}-{{ $current }}'"
                                    @if ($editMode && ! $swapLocked && ! $pastLocked)
                                        wire:model="draft.{{ $employee->id }}.{{ $date->toDateString() }}"
                                        @change="recolor($event.target)"
                                    @endif
                                    @disabled($locked)
                                    @if ($swapLocked)
                                        title="Jadwal ini terkait pengajuan tukar shift — tidak bisa diubah dari halaman jadwal."
                                    @elseif ($pastLocked)
                                        title="Tanggal sudah lewat - cuma bisa diubah oleh yang punya izin Edit Jadwal."
                                    @elseif (! $editMode)
                                        title="Klik &quot;Mulai Isi Jadwal&quot; dulu buat mengubah."
                                    @endif
                                    style="width:52px;padding:6px 2px;font-size:10.5px;text-align:center;font-weight:700;border:none;border-radius:5px;{{ $locked ? 'cursor:not-allowed;opacity:0.7;' : 'cursor:pointer;' }}color:{{ $currentFontColor }};{{ $isOff ? 'background:color-mix(in srgb, #dc2626 55%, white);' : ($currentColor ? "background:{$currentColor};" : 'background:var(--surface-alt);') }}">
                                    <option value="" {{ $current === '' ? 'selected' : '' }}>-</option>
                                    @foreach ($rowShiftTypes as $shiftType)
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
            <button type="button" wire:click="resetPeriod"
                wire:confirm="{{ $this->canEditPastDates ? "Hapus SEMUA roster {$this->periodLabel} (termasuk yang sudah dikoreksi manual, termasuk tanggal yang sudah lewat)? Tindakan ini tidak bisa dibatalkan." : "Hapus roster {$this->periodLabel} MULAI HARI INI sampai akhir bulan (termasuk yang sudah dikoreksi manual)? Tanggal yang sudah lewat tidak ikut dihapus. Tindakan ini tidak bisa dibatalkan." }}"
                class="btn-ghost" style="font-size:11px;padding:7px 12px;white-space:nowrap;color:var(--danger-text);">
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

    @script
    <script>
        Alpine.data('rosterFill', (styleMap = {}) => ({
            styleMap,
            fillMode: false,
            selecting: false,
            anchor: null,        // {r, c} titik awal seret pilih
            sel: null,           // {r, lo, hi} kolom terpilih di baris r (inklusif)
            handleDrag: false,
            previewHi: null,     // kolom tujuan sementara selama seret handle
            history: [],         // tumpukan undo: tiap entri = array {r,c,val} nilai LAMA sebelum 1 isi-pola

            lastPeriod: null,

            reset() { this.fillMode = false; this.history = []; this.clearSel(); },

            // Geser scroll horizontal biar tanggal 1 nempel di kiri (kolom "bulan lalu" ke-hide,
            // user scroll kiri kalau mau lihat). Kurangi lebar kolom "Karyawan" yang sticky.
            scrollToDay1() {
                const box = this.$el.querySelector('[data-scrollbox]');
                const day1 = box?.querySelector('[data-day1]');
                if (! box || ! day1) return;
                // Jumlah SEMUA kolom sticky (Karyawan, + Tim kalau jadwal ini pakai Tim) -
                // dulu cuma ambil <th> pertama, jadi salah pas ada 2 kolom sticky bertumpuk.
                const stickyW = Array.from(box.querySelectorAll('thead th.rf-sticky-col'))
                    .reduce((sum, el) => sum + el.getBoundingClientRect().width, 0);
                // pakai selisih rect (bukan offsetLeft — offsetParent-nya bukan si scrollbox)
                const delta = day1.getBoundingClientRect().left - box.getBoundingClientRect().left - stickyW;
                box.scrollLeft = Math.max(0, box.scrollLeft + delta);
            },
            clearSel() {
                this.selecting = false; this.handleDrag = false;
                this.anchor = null; this.sel = null; this.previewHi = null;
            },

            cellDown(e, r, c) {
                if (! this.fillMode) return;
                if (this.cellEl(r, c)?.dataset.locked === '1') return;
                e.preventDefault();
                this.selecting = true;
                this.anchor = { r, c };
                this.sel = { r, lo: c, hi: c };
            },

            cellEnter(r, c) {
                if (! this.selecting || ! this.anchor || r !== this.anchor.r) return;
                this.sel = { r, lo: Math.min(this.anchor.c, c), hi: Math.max(this.anchor.c, c) };
            },

            handleDown() {
                if (! this.sel) return;
                this.handleDrag = true;
                this.previewHi = this.sel.hi;
            },

            onMove(e) {
                if (! this.handleDrag || ! this.sel) return;
                const td = document.elementFromPoint(e.clientX, e.clientY)?.closest('td[data-r]');
                if (! td) return;
                const r = Number(td.dataset.r), c = Number(td.dataset.c);
                this.previewHi = (r === this.sel.r && c > this.sel.hi) ? c : this.sel.hi;
            },

            onUp() {
                if (this.handleDrag) this.applyFill();
                this.selecting = false;
                this.handleDrag = false;
            },

            // Ulang pola sel terpilih ke kanan: P P P L -> P P P L P P P L
            applyFill() {
                if (! this.sel || this.previewHi === null || this.previewHi <= this.sel.hi) return;
                const pattern = [];
                for (let c = this.sel.lo; c <= this.sel.hi; c++) pattern.push(this.selectAt(this.sel.r, c)?.value ?? '');
                if (! pattern.length) return;
                const r = this.sel.r;
                const snapshot = [];
                let k = 0;
                for (let c = this.sel.hi + 1; c <= this.previewHi; c++) {
                    const s = this.selectAt(r, c);
                    // ponytail: sel terkunci (tukar shift) dilewati tapi indeks pola tetap maju,
                    // biar pola tetap lurus sama kalender
                    const td = this.cellEl(r, c);
                    if (s && td?.dataset.locked !== '1' && td?.dataset.lead !== '1') {
                        snapshot.push({ r, c, val: s.value });
                        this.setVal(s, pattern[k % pattern.length]);
                    }
                    k++;
                }
                if (snapshot.length) {
                    this.history.push(snapshot);
                    if (this.history.length > 50) this.history.shift();
                }
                this.sel = { r, lo: this.sel.lo, hi: this.previewHi };
                this.previewHi = null;
            },

            // Ctrl+Z / tombol Undo — kembalikan nilai sel dari isi-pola terakhir.
            undo() {
                const last = this.history.pop();
                if (! last) return;
                for (const { r, c, val } of last) {
                    const s = this.selectAt(r, c);
                    const td = this.cellEl(r, c);
                    if (s && td?.dataset.locked !== '1' && td?.dataset.lead !== '1') this.setVal(s, val);
                }
                this.clearSel();
            },

            setVal(s, val) {
                if (s.value === val) return;
                s.value = val;
                this.recolor(s);
                // wire:model (deferred) nyerap lewat 'input'; 'change' buat jaga-jaga
                s.dispatchEvent(new Event('input', { bubbles: true }));
                s.dispatchEvent(new Event('change', { bubbles: true }));
            },

            // Samakan background/warna teks select sama nilainya sekarang — dipanggil pas drag/undo
            // dan pas user ganti manual (wire:model deferred, kalau nggak dipanggil warnanya baru
            // ikut pas Simpan).
            recolor(s) {
                const st = this.styleMap[s.value] ?? this.styleMap[''];
                if (! st) return;
                s.style.background = st.bg;
                s.style.color = st.fg;
            },

            cellEl(r, c) { return this.$el.querySelector(`td[data-r="${r}"][data-c="${c}"]`); },
            selectAt(r, c) { return this.cellEl(r, c)?.querySelector('select'); },

            isSel(r, c) { return !! (this.fillMode && this.sel && this.sel.r === r && c >= this.sel.lo && c <= this.sel.hi); },
            isPreview(r, c) { return !! (this.handleDrag && this.sel && this.sel.r === r && this.previewHi !== null && c > this.sel.hi && c <= this.previewHi); },
            cellClass(r, c) { return { 'rf-sel': this.isSel(r, c), 'rf-preview': this.isPreview(r, c) }; },
        }));
    </script>
    @endscript
</div>
