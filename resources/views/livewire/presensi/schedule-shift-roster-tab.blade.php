@php
    // Oncall assignable langsung di roster sama seperti Pagi/Siang/Malam/Middle (2026-09-14,
    // revisi - bukan lagi cuma didapat lewat Extra Shift pas libur, lihat
    // planning/2026-09-14-oncall-middle-redesign.md).
    $assignableShiftTypes = $schedule->shiftTypes;

    // Peta warna per nilai sel buat Alpine — biar drag/undo bisa langsung ganti background sel
    // tanpa nunggu re-render Livewire (wire:model deferred).
    $shiftStyleMap = $schedule->shiftTypes->mapWithKeys(fn ($t) => [
        (string) $t->id => ['bg' => $t->color ?: 'var(--surface-alt)', 'fg' => $t->font_color ?: 'var(--text-body)'],
    ])->all();
    // Warna sel Libur (off_day) - bisa diatur per jadwal (schedules.off_bg_color/off_font_color),
    // kosong = default lama (merah muda + teks putih), lihat migration add_off_colors_to_schedules_table.
    $offBg = $schedule->off_bg_color ?: 'color-mix(in srgb, #dc2626 55%, white)';
    $offFont = $schedule->off_font_color ?: '#fff';
    $shiftStyleMap['off'] = ['bg' => $offBg, 'fg' => $offFont];
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
        .rf-scrollbox { max-height:100vh; }
        .rf-team-btn { flex-shrink:0; width:26px; height:26px; border-radius:7px; border:1px solid var(--border-soft); background:var(--surface-alt); color:var(--text-muted); font-size:11px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; }
        .rf-team-btn:hover { background:var(--border-soft); color:var(--text-heading); }
        /* HP (2026-09-21): tabel penjadwalan lebih pendek + font header tanggal, nama karyawan, dan kode shift dikecilkan */
        @media (max-width: 767px) {
            .rf-scrollbox { max-height:60vh; }
            .rf-table { font-size:10px !important; }
            .rf-table th { font-size:10px !important; }
            .rf-table th:not(.rf-sticky-col) { padding:6px 3px !important; min-width:46px !important; }
            .rf-table th.rf-sticky-col, .rf-table td.rf-name { padding-left:8px !important; padding-right:8px !important; }
            .rf-team-btn { width:28px; height:28px; }
            .rf-table select { width:44px !important; padding:5px 1px !important; font-size:9px !important; }
        }
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
        .team-hover-panel { display:none; }
        .team-hover:hover .team-hover-panel, .team-hover:focus-within .team-hover-panel { display:block; }
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
            @if ($schedule->use_teams && $this->canSetTeamGroupMode)
                <div style="margin-top:16px;display:flex;align-items:center;gap:4px;background:var(--surface-alt);border-radius:8px;padding:3px;">
                    <button type="button" wire:click="setTeamGroupMode('row')" title="Tim sebagai baris pemisah"
                        class="{{ $teamGroupMode === 'row' ? 'btn-primary' : 'btn-ghost' }}" style="font-size:10.5px;padding:6px 10px;white-space:nowrap;">
                        <i class="fa-solid fa-table-list"></i> Baris
                    </button>
                    <button type="button" wire:click="setTeamGroupMode('column')" title="Tim sebagai kolom di samping nama karyawan"
                        class="{{ $teamGroupMode === 'column' ? 'btn-primary' : 'btn-ghost' }}" style="font-size:10.5px;padding:6px 10px;white-space:nowrap;">
                        <i class="fa-solid fa-table-columns"></i> Kolom
                    </button>
                </div>
            @endif
            {{-- Export/Import/Generate/Isi Jadwal dikunci 2+ bulan ke depan dari bulan berjalan
            (instruksi user 2026-09-16, sama threshold-nya sama ScheduleShiftTeamTab::periodLocked)
            - grid tetap kelihatan (view-only), cuma aksi tulis/unduhnya yang disembunyikan. --}}
            @unless ($this->periodLocked)
            <button type="button" wire:click="exportExcel" class="btn-success" style="font-size:11px;padding:7px 12px;white-space:nowrap;margin-top:16px;">
                <i class="fa-solid fa-file-excel"></i> Export Excel
            </button>
            @if ($this->canEditJadwal)
                <div style="margin-top:16px;">
                    {{-- Sama alasannya dengan tombol Isi Jadwal - approver yang lagi ada pengajuan
                    menunggu wajib Setujui/Tolak dulu, bukan import di atas draft yang belum
                    diputuskan (instruksi user). --}}
                    <label class="btn-info" style="font-size:11px;padding:7px 12px;white-space:nowrap;margin:0;{{ $this->canReviewRosterChange ? 'opacity:0.5;cursor:not-allowed;' : 'cursor:pointer;' }}"
                        @if ($this->canReviewRosterChange) title="Ada pengajuan menunggu keputusan Anda - Setujui/Tolak dulu di banner atas sebelum import." @endif>
                        <i class="fa-solid fa-file-import"></i> Import Excel
                        <input type="file" wire:model="importFile" accept=".xlsx,.xls" style="display:none;" @disabled($this->canReviewRosterChange)>
                    </label>
                    <div wire:loading wire:target="importFile" style="font-size:10.5px;color:var(--text-muted);margin-top:2px;">Mengimpor...</div>
                    @error('importFile') <div style="font-size:10.5px;color:var(--danger-text);margin-top:2px;">{{ $message }}</div> @enderror
                </div>
            @endif
            @endunless
        </div>

        @if ($this->openRosterChangeRequest)
            <div style="margin-top:12px;padding:10px 14px;border-radius:10px;background:rgba(217,119,6,0.1);border:1px solid rgba(217,119,6,0.3);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div style="font-size:12px;color:#92400e;">
                    <i class="fa-solid fa-hourglass-half"></i>
                    Ada perubahan jadwal diajukan <strong>{{ $this->openRosterChangeRequest->requester?->name }}</strong>, menunggu persetujuan.
                    Sel yang diajukan ditandai dim di grid.
                </div>
                @if ($this->canReviewRosterChange)
                    <div style="display:flex;gap:8px;">
                        <button type="button" wire:click="approveRosterChange" wire:confirm="Setujui perubahan jadwal ini?" class="btn-primary" style="font-size:11px;padding:6px 12px;">
                            <i class="fa-solid fa-check"></i> Setujui
                        </button>
                        <button type="button" wire:click="rejectRosterChange" wire:confirm="Tolak perubahan jadwal ini?" class="btn-ghost" style="font-size:11px;padding:6px 12px;color:var(--danger-text);border-color:var(--danger-soft-border);">
                            <i class="fa-solid fa-xmark"></i> Tolak
                        </button>
                    </div>
                @endif
            </div>
        @endif

        @if ($this->periodLocked)
            <p style="font-size:11px;color:var(--text-faint);margin:0;">Bulan ini belum bisa diisi/di-export/di-import — baru bisa mulai bulan depan ({{ now()->addMonth()->locale('id')->translatedFormat('F Y') }}).</p>
        @else
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
                    {{-- Approver yang lagi ada pengajuan menunggu di jadwal ini WAJIB memutuskan
                    (Setujui/Tolak) dulu lewat banner di atas, bukan malah mulai edit sendiri di
                    atas draft yang belum diputuskan - instruksi user. --}}
                    <button type="button" wire:click="startEdit" class="btn-primary" style="font-size:11px;padding:7px 12px;white-space:nowrap;"
                        @disabled($this->canReviewRosterChange)
                        @if ($this->canReviewRosterChange) title="Ada pengajuan menunggu keputusan Anda - Setujui/Tolak dulu di banner atas sebelum isi jadwal." @endif>
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
        @endif
    </div>

    {{-- Keterangan warna kode shift — sama kayak tab Template Shift, biar konsisten. Kalau
    jadwal ini pakai Regu, daftar shift FLAT tadi menyesatkan (tiap Regu bisa beda jatah shift,
    lihat ScheduleShiftTeam::shiftTypes) — diganti badge per Regu, hover buat lihat shift jatah
    Regu itu (instruksi user 2026-09-15), bukan nge-dump semua shift schedule tanpa konteks. --}}
    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:10px;font-size:13px;color:var(--text-muted);">
        @if ($schedule->use_teams)
            <span style="font-weight:700;color:var(--text-heading);">Regu:</span>
            @forelse ($this->teams as $team)
                <div class="team-hover" tabindex="0" style="position:relative;outline:none;">
                    <strong style="cursor:pointer;font-size:12.5px;font-weight:800;padding:3px 10px;border-radius:6px;color:#fff;background:{{ $team->color ?: 'var(--text-faint)' }};display:inline-flex;align-items:center;gap:5px;">
                        {{ $team->name }} <i class="fa-solid fa-chevron-down" style="font-size:8px;opacity:0.85;"></i>
                    </strong>
                    <div class="team-hover-panel" style="position:absolute;top:calc(100% + 6px);left:0;z-index:15;background:var(--surface);border:1px solid var(--border);border-radius:10px;box-shadow:var(--shadow-card);padding:10px;min-width:210px;">
                        <div style="font-size:10.5px;font-weight:700;color:var(--text-muted);margin-bottom:6px;white-space:nowrap;">Jatah shift {{ $team->name }}</div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;">
                            @forelse ($team->shiftTypes as $shiftType)
                                <strong style="font-size:11px;font-weight:800;padding:2px 8px;border-radius:5px;white-space:nowrap;color:{{ $shiftType->font_color ?: '#fff' }};background:{{ $shiftType->color ?: 'var(--text-faint)' }};">{{ $shiftType->code }} ({{ mb_strtoupper($shiftType->name) }})</strong>
                            @empty
                                <span style="font-size:11px;color:var(--text-faint);white-space:nowrap;">Semua shift (tidak dibatasi)</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            @empty
                <span style="font-size:12px;">Belum ada Regu — atur di tab Regu.</span>
            @endforelse
        @else
            <span style="font-weight:700;color:var(--text-heading);">Keterangan:</span>
            @foreach ($schedule->shiftTypes as $shiftType)
                <strong style="font-size:12.5px;font-weight:800;padding:3px 10px;border-radius:6px;color:{{ $shiftType->font_color ?: '#fff' }};background:{{ $shiftType->color ?: 'var(--text-faint)' }};">{{ $shiftType->code }} ({{ mb_strtoupper($shiftType->name) }})</strong>
            @endforeach
        @endif
    </div>

    <div x-show="fillMode" class="rf-hint" x-cloak>
        <i class="fa-solid fa-table-cells"></i>
        <span>Mode <strong>Isi Cepat</strong> aktif — dropdown sel dimatikan. Seret mendatar buat pilih sel, lalu seret kotak biru di pojok kanan-bawah buat mengulang pola. Matikan tombol "Isi Cepat" buat edit sel satu-satu lagi.</span>
    </div>
    {{-- Wrapper Alpine untuk menu titik-tiga di samping nama (state `menu` = karyawan yang dipilih). --}}
    <div x-data="{ menu: null }" @keydown.escape.window="menu = null">
    <div data-scrollbox class="rf-scrollbox" :class="{ 'rf-on': fillMode, 'rf-grid-on': fillMode }" style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:auto;">
        <table class="rf-table" style="border-collapse:collapse;font-size:11.5px;">
            <thead>
                <tr style="border-bottom:1.5px solid var(--border);">
                    @if ($schedule->use_teams && $teamGroupMode === 'column')
                        <th class="rf-sticky-col" style="text-align:center;padding:10px 8px;white-space:nowrap;min-width:130px;position:sticky;left:0;top:0;background:var(--surface);z-index:4;">Tim</th>
                    @endif
                    <th class="rf-sticky-col" style="text-align:left;padding:10px 14px;white-space:nowrap;position:sticky;left:{{ ($schedule->use_teams && $teamGroupMode === 'column') ? '130px' : '0' }};top:0;background:var(--surface);z-index:4;">Karyawan</th>
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
                        // displayRoster (approved + overlay draft), bukan roster approved polos -
                        // biar sel yang lagi diajukan (belum disetujui) tetap kelihatan di grid,
                        // ditandai dim lewat isDraftCell di bawah (instruksi user 2026-09-28).
                        $userRoster = $this->displayRoster[$employee->id] ?? [];
                        $userDraft = $this->draftRoster[$employee->id] ?? [];
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
                    @if ($schedule->use_teams && $isNewTeamGroup && $teamGroupMode === 'row')
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
                        @if ($schedule->use_teams && $teamGroupMode === 'column' && $isNewTeamGroup)
                            {{-- Kolom Tim rowspan — cuma dirender sekali per grup (baris pertamanya),
                                 menutupi semua baris anggota tim itu lewat rowspan. --}}
                            <td rowspan="{{ $this->teamGroupSizes[$teamGroupKey] ?? 1 }}" class="rf-sticky-col" style="padding:6px 10px;text-align:center;font-size:11px;font-weight:800;min-width:130px;vertical-align:middle;position:sticky;left:0;color:{{ $teamColor ? '#fff' : 'var(--text-heading)' }};background:{{ $teamColor ?? 'var(--surface-alt)' }};border-right:1px solid var(--border-soft);z-index:2;">
                                {{ $teamInfo['name'] ?? 'Tanpa Tim' }}
                                @if ($groupShiftCodes)
                                    <br><span style="font-weight:600;font-size:9.5px;opacity:0.85;">{{ $groupShiftCodes }}</span>
                                @endif
                            </td>
                        @endif
                        <td class="rf-name" style="padding:6px 12px;white-space:nowrap;font-weight:600;position:sticky;left:{{ ($schedule->use_teams && $teamGroupMode === 'column') ? '130px' : '0' }};background:var(--surface);z-index:1;">
                            @php
                                // Menu titik-tiga: "Ganti Tim" (jadwal pakai Tim) dan "Urutkan" (grup minimal 2 orang).
                                $menuCanTeam = $this->canChangeTeam;
                                $menuCanSort = $this->canManageOrder && ($this->teamGroupSizes[$teamGroupKey] ?? 1) > 1;
                            @endphp
                            @if ($menuCanTeam || $menuCanSort)
                                <span style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                    <span>{{ $employee->name }}</span>
                                    <button type="button" class="rf-team-btn" title="Aksi {{ $employee->name }}" aria-label="Aksi {{ $employee->name }}"
                                        @click="menu = { id: '{{ $employee->id }}', name: @js($employee->name), team: {{ $menuCanTeam ? 'true' : 'false' }}, sort: {{ $menuCanSort ? 'true' : 'false' }} }">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                </span>
                            @else
                                {{ $employee->name }}
                            @endif
                        </td>
                        @foreach ($this->leadDates as $ld)
                            @php
                                $lval = $userRoster[$ld->toDateString()] ?? '';
                                $lIsOff = $lval === 'off';
                                $lType = $schedule->shiftTypes->firstWhere('id', $lval);
                                $lColor = $lType?->color;
                                $lFont = $lIsOff ? '#fff' : ($lType?->font_color ?: 'var(--text-faint)');
                                $rfC = $loop->index - $leadCount; // -7..-1
                            @endphp
                            <td class="rf-cell" data-r="{{ $rfRow }}" data-c="{{ $rfC }}" data-locked="0" data-lead="1"
                                @mousedown="cellDown($event, {{ $rfRow }}, {{ $rfC }})" @mouseenter="cellEnter({{ $rfRow }}, {{ $rfC }})"
                                :class="cellClass({{ $rfRow }}, {{ $rfC }})"
                                title="Bulan sebelumnya — konteks buat nyambung pola, tidak bisa diedit"
                                style="padding:2px;text-align:center;position:relative;">
                                <select disabled
                                    style="width:52px;padding:6px 2px;font-size:10.5px;text-align:center;font-weight:700;border:none;border-radius:5px;opacity:0.5;cursor:not-allowed;color:{{ $lFont }};{{ $lIsOff ? "background:{$offBg};" : ($lColor ? "background:{$lColor};" : 'background:var(--surface-alt);') }}">
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
                                $currentFontColor = $isOff ? '#fff' : ($currentType?->font_color ?: 'var(--text-body)');
                                // Shift tersimpan TAPI di luar daftar shift Tim karyawan ini (mis. Tim
                                // baru dibatasi SETELAH data ini ada, atau data dari import lama) -
                                // tanpa <option> buat nilai ini, <select> HTML otomatis jatuh ke opsi
                                // pertama ("-" kosong) walau $current valid - data kelihatan "hilang"
                                // padahal cuma dropdown-nya tidak punya opsi buat menunjukkannya
                                // (2026-09-14, dilaporkan user). Ditambahkan sebagai opsi ekstra di
                                // bawah, BUKAN masuk $rowShiftTypes - supaya tetap tidak bisa dipilih
                                // ulang lewat Isi Cepat/manual kalau memang di luar jatah Tim.
                                $currentOutsideTeam = $currentType && $current !== '' && ! $isOff && ! $rowShiftTypes->contains('id', $current);
                                // Sel yang jadwalnya lagi/sudah dipakai pengajuan tukar shift -> dikunci
                                // total, tidak bisa diedit dari halaman jadwal (biar tidak bentrok sama
                                // eksekusi swap). Lihat ScheduleShiftRosterTab::getSwapCellsProperty().
                                $swapLocked = isset($this->swapCells[$employee->id.'|'.$date->toDateString()]);
                                // Sel Cuti/Izin disetujui otomatis - selalu ditandai (badge + kode
                                // C/Izin), tapi cuma DIKUNCI dari edit buat yang bukan pemegang
                                // Update:Schedule (Edit Jadwal) - instruksi user 2026-09-24. Lihat
                                // ScheduleShiftRosterTab::getLeaveCellsProperty()/getCanOverrideLeaveLockProperty().
                                $leaveType = $this->leaveCells[$employee->id.'|'.$date->toDateString()] ?? null;
                                $isLeaveCell = $leaveType !== null;
                                $leaveLocked = $isLeaveCell && ! $this->canOverrideLeaveLock;
                                // Yang cuma punya Update:ShiftScheduleAssignments (bukan Edit Jadwal) BOLEH
                                // pilih tanggal lampau juga (dicabut 2026-09-28, lihat docblock saveRoster())
                                // - tetap bisa diedit di grid, cuma tidak langsung tertulis, lewat
                                // ScheduleRosterChangeService::submitForApproval() sampai disetujui atasan.
                                $locked = ! $editMode || $swapLocked || $leaveLocked;
                                $rfR = $loop->parent->index;
                                $rfC = $loop->index;
                                // Sel ini lagi ada pengajuan belum diputuskan - dim (opacity
                                // dikurangi) biar kebeda dari yang sudah approved, baik pas lihat
                                // maupun pas lanjut edit (instruksi user 2026-09-28).
                                $isDraftCell = isset($userDraft[$date->toDateString()]);
                            @endphp
                            <td class="rf-cell" data-r="{{ $rfR }}" data-c="{{ $rfC }}" data-locked="{{ ($swapLocked || $leaveLocked) ? '1' : '0' }}"
                                @mousedown="cellDown($event, {{ $rfR }}, {{ $rfC }})" @mouseenter="cellEnter({{ $rfR }}, {{ $rfC }})"
                                :class="cellClass({{ $rfR }}, {{ $rfC }})"
                                style="padding:2px;text-align:center;position:relative;">
                                <template x-if="fillMode && sel && ! selecting && sel.r === {{ $rfR }} && sel.hi === {{ $rfC }}">
                                    <div class="rf-handle" @mousedown.stop.prevent="handleDown()"></div>
                                </template>
                                @if ($swapLocked)
                                    <button type="button" wire:click="$dispatch('open-schedule-swap-history', { userId: '{{ $employee->id }}', date: '{{ $date->toDateString() }}' })" title="Jadwal ini terkait pengajuan tukar shift — tidak bisa diubah dari sini. Klik untuk lihat riwayat."
                                        style="position:absolute;top:0;right:0;z-index:3;background:none;border:none;padding:1px 2px;cursor:pointer;color:#b45309;font-size:8.5px;line-height:1;">
                                        <i class="fa-solid fa-right-left"></i>
                                    </button>
                                @elseif ($isLeaveCell)
                                    <span title="{{ $leaveType === 'cuti' ? 'Cuti' : 'Izin' }} disetujui otomatis{{ $leaveLocked ? ' — tidak bisa diubah dari sini.' : ' — kamu bisa mengubahnya (Edit Jadwal).' }}"
                                        style="position:absolute;top:0;right:0;z-index:3;padding:1px 2px;color:#000;font-size:8.5px;line-height:1;">
                                        <i class="fa-solid fa-file-medical"></i>
                                    </span>
                                @elseif ($isDraftCell)
                                    {{-- Pola sama dengan ikon tukar shift/cuti di atas - penanda kecil di pojok
                                    sel, bukan cuma andalkan opacity dim (instruksi user). --}}
                                    <span title="Diajukan, belum disetujui oleh HC/Kepegawaian."
                                        style="position:absolute;top:0;right:0;z-index:3;padding:1px 2px;color:#b45309;font-size:8.5px;line-height:1;">
                                        <i class="fa-solid fa-hourglass-half"></i>
                                    </span>
                                @endif
                                <select
                                    {{-- wire:key ikut nilainya, bukan cuma posisi - morphdom kadang cuma
                                    patch atribut "selected" tanpa nyamain properti .value/tampilan visual
                                    <select> yang sudah kepegang browser, jadi abis Simpan warna berubah
                                    tapi teksnya masih nilai lama sampai refresh manual. wire:key beda ->
                                    dianggap elemen baru -> browser bikin ulang dari nol, bukan di-patch. --}}
                                    wire:key="'cell-{{ $employee->id }}-{{ $date->toDateString() }}-{{ $current }}'"
                                    @if ($editMode && ! $swapLocked && ! $leaveLocked)
                                        wire:model="draft.{{ $employee->id }}.{{ $date->toDateString() }}"
                                        @change="recolor($event.target)"
                                    @endif
                                    @disabled($locked)
                                    @if ($isDraftCell)
                                        title="Diajukan, belum disetujui oleh HC/Kepegawaian."
                                    @elseif ($swapLocked)
                                        title="Jadwal ini terkait pengajuan tukar shift — tidak bisa diubah dari halaman jadwal."
                                    @elseif ($leaveLocked)
                                        title="{{ $leaveType === 'cuti' ? 'Cuti' : 'Izin' }} disetujui otomatis — tidak bisa diubah dari halaman jadwal."
                                    @elseif ($isLeaveCell)
                                        title="{{ $leaveType === 'cuti' ? 'Cuti' : 'Izin' }} disetujui otomatis — kamu bisa mengubahnya karena punya izin Edit Jadwal."
                                    @elseif ($editMode && ! $this->canEditPastDates && $date->lt(today()))
                                        title="Tanggal sudah lewat — perubahan diajukan dulu ke HC/Kepegawaian, tidak langsung tersimpan."
                                    @elseif (! $editMode && $current === '')
                                        title="Lepas Dinas"
                                    @elseif (! $editMode)
                                        title="Klik &quot;Mulai Isi Jadwal&quot; dulu buat mengubah."
                                    @endif
                                    {{-- opacity locked dikurangi TIPIS aja (0.92, dulu 0.7) - kebanyakan bikin
                                    warna sel beda jauh dari warna shift sebenarnya (dilaporkan user 2026-09-16).
                                    Sel draft (belum disetujui) dikurangi LEBIH jauh (0.55) - beda kasat mata dari
                                    sel biasa, baik lagi editMode (karu lanjut edit proposalnya) maupun view. --}}
                                    style="width:52px;padding:6px 2px;font-size:10.5px;text-align:center;font-weight:700;border:none;border-radius:5px;{{ $isDraftCell ? 'opacity:0.55;' : ($locked ? 'opacity:0.92;' : '') }}{{ $locked ? 'cursor:not-allowed;' : 'cursor:pointer;' }}color:{{ $isLeaveCell ? '#000' : $currentFontColor }};{{ $isLeaveCell ? 'background:'.($leaveType === 'cuti' ? '#EF4444' : '#0EA5E9').';' : ($isOff ? "background:{$offBg};" : ($currentColor ? "background:{$currentColor};" : 'background:var(--surface-alt);')) }}">
                                    <option value="" {{ $current === '' ? 'selected' : '' }}>{{ $editMode ? '-' : 'N/A' }}</option>
                                    @if ($currentOutsideTeam)
                                        <option value="{{ $current }}" selected title="Di luar daftar shift Tim karyawan ini">{{ $currentType->code }} &#9888;</option>
                                    @endif
                                    @foreach ($rowShiftTypes as $shiftType)
                                        <option value="{{ $shiftType->id }}" {{ $current === $shiftType->id ? 'selected' : '' }}>{{ $shiftType->code }}</option>
                                    @endforeach
                                    <option value="off" {{ $isOff ? 'selected' : '' }}>{{ $isLeaveCell ? ($leaveType === 'cuti' ? 'C' : 'Izin') : 'L' }}</option>
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

        {{-- Sheet aksi karyawan (dibuka tombol titik-tiga): Ganti Tim / Urutkan. Modal, bukan dropdown,
        karena kolom nama sticky di dalam kotak scroll - dropdown absolut akan terpotong. --}}
        <template x-if="menu">
            <div class="modal-overlay" @click="menu = null">
                <div class="modal-box" @click.stop style="max-width:300px;">
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Aksi Karyawan</h3>
                    <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;" x-text="menu.name + ' · ' + @js($this->periodLabel)"></p>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <template x-if="menu.team">
                            <button type="button" @click="$wire.openTeamPicker(menu.id); menu = null"
                                style="display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:10px;border:1.5px solid var(--border-soft);background:var(--surface);cursor:pointer;font-family:inherit;text-align:left;font-size:13px;font-weight:600;color:var(--text-heading);">
                                <i class="fa-solid fa-people-arrows" style="width:16px;color:var(--text-muted);"></i> Ganti Tim
                            </button>
                        </template>
                        <template x-if="menu.sort">
                            <button type="button" @click="$wire.openReorder(menu.id); menu = null"
                                style="display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:10px;border:1.5px solid var(--border-soft);background:var(--surface);cursor:pointer;font-family:inherit;text-align:left;font-size:13px;font-weight:600;color:var(--text-heading);">
                                <i class="fa-solid fa-sort" style="width:16px;color:var(--text-muted);"></i> Urutkan
                            </button>
                        </template>
                    </div>
                    <button type="button" @click="menu = null" class="btn-ghost" style="width:100%;justify-content:center;margin-top:14px;">Batal</button>
                </div>
            </div>
        </template>
    </div>

    {{-- Urutkan manual: pindahkan 1 karyawan ke sebelum/sesudah karyawan lain di grup yang sama
    (tim yang sama / sama-sama Tanpa Tim). Tersimpan di DB (lihat saveReorder()). --}}
    @if ($reorderUserId && $this->canManageOrder)
        @php
            $rUser = $this->employees->firstWhere('id', $reorderUserId);
            $rTeamName = $this->employeeTeamInfo[$reorderUserId]['name'] ?? 'Tanpa Tim';
            $rOthers = $this->groupMembersOf($reorderUserId)->reject(fn ($u) => $u->id === $reorderUserId);
        @endphp
        <div class="modal-overlay" wire:click="closeReorder">
            <div class="modal-box" onclick="event.stopPropagation()" style="max-width:360px;">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Urutkan</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $rUser?->name }} &middot; {{ $rTeamName }}</p>

                <label class="form-label">Pindahkan ke</label>
                <div style="display:flex;gap:8px;margin-bottom:12px;">
                    @foreach (['before' => 'Sebelum', 'after' => 'Sesudah'] as $val => $lbl)
                        <label style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:9px 10px;border-radius:10px;cursor:pointer;font-size:12.5px;font-weight:600;border:1.5px solid {{ $reorderPosition === $val ? 'var(--brand)' : 'var(--border-soft)' }};color:var(--text-heading);">
                            <input type="radio" wire:model.live="reorderPosition" value="{{ $val }}" style="display:none;"> {{ $lbl }}
                        </label>
                    @endforeach
                </div>

                <label class="form-label">{{ $reorderPosition === 'after' ? 'Sesudah' : 'Sebelum' }} siapa?</label>
                <select wire:model="reorderTargetId" class="form-input">
                    <option value="">-- Pilih karyawan --</option>
                    @foreach ($rOthers as $other)
                        <option value="{{ $other->id }}">{{ $other->name }}</option>
                    @endforeach
                </select>
                @error('reorderTargetId') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror

                <div style="display:flex;gap:8px;margin-top:16px;">
                    <button type="button" wire:click="closeReorder" class="btn-ghost" style="flex:1;justify-content:center;">Batal</button>
                    <button type="button" wire:click="saveReorder" wire:loading.attr="disabled" class="btn-primary" style="flex:1;justify-content:center;">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Ganti tim 1 karyawan untuk bulan yang lagi dibuka (2026-09-21) - gerbang & batas bulan sama
    persis tab Tim (canChangeTeam). --}}
    @if ($teamPickerUserId && $this->canChangeTeam)
        @php
            $pickerUser = $this->employees->firstWhere('id', $teamPickerUserId);
            $pickerTeamId = $pickerUser ? ($this->employeeTeamInfo[$pickerUser->id]['id'] ?? '') : '';
        @endphp
        <div class="modal-overlay" wire:click="closeTeamPicker">
            <div class="modal-box" onclick="event.stopPropagation()" style="max-width:340px;">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Ganti Tim</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $pickerUser?->name }} &middot; {{ $this->periodLabel }}</p>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    @foreach ($this->teams as $team)
                        <button type="button" wire:click="setMemberTeam('{{ $team->id }}')" wire:loading.attr="disabled"
                            style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;border:1.5px solid {{ $pickerTeamId === $team->id ? 'var(--brand)' : 'var(--border-soft)' }};background:var(--surface);cursor:pointer;font-family:inherit;text-align:left;">
                            <span style="width:12px;height:12px;border-radius:50%;background:{{ $team->color ?? '#042c6c' }};flex-shrink:0;"></span>
                            <span style="flex:1;font-size:13px;font-weight:600;color:var(--text-heading);">{{ $team->name }}</span>
                            @if ($pickerTeamId === $team->id) <i class="fa-solid fa-check" style="color:var(--brand);font-size:12px;"></i> @endif
                        </button>
                    @endforeach
                    <button type="button" wire:click="setMemberTeam('')" wire:loading.attr="disabled"
                        style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;border:1.5px solid {{ $pickerTeamId === '' ? 'var(--brand)' : 'var(--border-soft)' }};background:var(--surface);cursor:pointer;font-family:inherit;text-align:left;">
                        <span style="width:12px;height:12px;border-radius:50%;border:1.5px dashed var(--text-faint);flex-shrink:0;"></span>
                        <span style="flex:1;font-size:13px;font-weight:600;color:var(--text-muted);">Tanpa Tim</span>
                        @if ($pickerTeamId === '') <i class="fa-solid fa-check" style="color:var(--brand);font-size:12px;"></i> @endif
                    </button>
                </div>
                <button type="button" wire:click="closeTeamPicker" class="btn-ghost" style="width:100%;justify-content:center;margin-top:14px;">Batal</button>
            </div>
        </div>
    @endif

    <div style="margin-top:12px;display:flex;gap:8px;">
        <button type="button" wire:click="toggleLogs" class="btn-ghost" style="font-size:11px;padding:7px 12px;white-space:nowrap;">
            <i class="fa-solid fa-clock-rotate-left"></i> {{ $showLogs ? 'Sembunyikan' : 'Lihat' }} Riwayat Perubahan
        </button>
        {{-- Reset Jadwal DINONAKTIFKAN sementara (instruksi user 2026-09-29) - method-nya menulis
        langsung ke DB, bypass alur approval Perubahan Jadwal (ScheduleRosterChangeService) sama
        sekali beda dari edit sel manual yang sudah lewat approval. Jangan diaktifkan lagi sebelum
        itu dibenerin (resetPeriod() ikut lewat approval juga, atau sengaja tetap langsung dengan
        alasan jelas) - lihat resetPeriod() di ScheduleShiftRosterTab.php, sudah di-abort juga di
        server, bukan cuma disembunyikan di sini.
        @if ($this->canEditJadwal && $period >= now()->format('Y-m'))
            <button type="button" wire:click="resetPeriod"
                wire:confirm="{{ $this->canEditPastDates ? "Hapus SEMUA roster {$this->periodLabel} (termasuk yang sudah dikoreksi manual, termasuk tanggal yang sudah lewat)? Tindakan ini tidak bisa dibatalkan." : "Hapus roster {$this->periodLabel} MULAI HARI INI sampai akhir bulan (termasuk yang sudah dikoreksi manual)? Tanggal yang sudah lewat tidak ikut dihapus. Tindakan ini tidak bisa dibatalkan." }}"
                class="btn-ghost" style="font-size:11px;padding:7px 12px;white-space:nowrap;color:var(--danger-text);">
                <i class="fa-solid fa-rotate-left"></i> Reset Jadwal
            </button>
        @endif --}}
        @if ($this->canResetOrder)
            <button type="button" wire:click="resetOrderByJoinDate"
                wire:confirm="Kembalikan urutan karyawan {{ $this->periodLabel }} ke urutan tanggal bergabung (terlama dulu)? Urutan manual yang sudah diatur (di dalam tim maupun Tanpa Tim) akan diganti."
                class="btn-ghost" style="font-size:11px;padding:7px 12px;white-space:nowrap;">
                <i class="fa-solid fa-arrow-down-1-9"></i> Urutkan per Tanggal Bergabung
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
