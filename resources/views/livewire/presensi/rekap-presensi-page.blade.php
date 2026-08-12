@php
    $statusStyle = [
        \App\Enum\AttendanceStatus::PRESENT->value => ['label' => 'Hadir', 'color' => '#047C54', 'bg' => 'rgba(4,124,84,0.1)'],
        \App\Enum\AttendanceStatus::LATE->value => ['label' => 'Terlambat', 'color' => '#b45309', 'bg' => 'rgba(180,83,9,0.1)'],
        \App\Enum\AttendanceStatus::PERMIT->value => ['label' => 'Izin', 'color' => '#1d4ed8', 'bg' => 'rgba(29,78,216,0.1)'],
        \App\Enum\AttendanceStatus::LEAVE->value => ['label' => 'Cuti', 'color' => '#0f766e', 'bg' => 'rgba(15,118,110,0.1)'],
        \App\Enum\AttendanceStatus::SICK->value => ['label' => 'Sakit', 'color' => '#be185d', 'bg' => 'rgba(190,24,93,0.1)'],
        \App\Enum\AttendanceStatus::OVERTIME->value => ['label' => 'Lembur', 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,0.1)'],
        \App\Enum\AttendanceStatus::ABSENT->value => ['label' => 'Tidak Hadir', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.1)'],
    ];
@endphp
<div>
<x-page-header title="Rekap Presensi" subtitle="Rekap kehadiran karyawan - mode harian atau bulanan" />

@if ($this->canSync && $mode === 'harian')
    <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
        <button type="button" wire:click="openSyncModal" class="btn-primary">
            <i class="fa-solid fa-arrow-rotate-right"></i> Sync Presensi
        </button>
    </div>
@endif

<div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;margin-bottom:16px;">
    <button type="button" wire:click="setMode('harian')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $mode === 'harian' ? 'var(--brand)' : 'transparent' }};color:{{ $mode === 'harian' ? '#fff' : 'var(--text-muted)' }};box-shadow:{{ $mode === 'harian' ? 'var(--shadow-card)' : 'none' }};">Hari Ini</button>
    <button type="button" wire:click="setMode('bulanan')" style="padding:4px 12px;font-size:11.5px;font-weight:600;border:none;border-radius:6px;cursor:pointer;font-family:inherit;background:{{ $mode === 'bulanan' ? 'var(--brand-success)' : 'transparent' }};color:{{ $mode === 'bulanan' ? '#fff' : 'var(--text-muted)' }};box-shadow:{{ $mode === 'bulanan' ? 'var(--shadow-card)' : 'none' }};">Periode</button>
</div>

<div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
    @if ($this->canBrowseAllCompanies)
        <div style="min-width:220px;">
            <label class="form-label">Perusahaan</label>
            <select wire:model.live="companyId" class="form-input">
                <option value="">Semua perusahaan</option>
                @foreach ($this->companyOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($mode === 'bulanan')
        <div style="min-width:170px;">
            <label class="form-label">Dari Tanggal</label>
            <input type="date" class="form-input" wire:model.live="date_from">
        </div>

        <div style="min-width:170px;">
            <label class="form-label">Sampai Tanggal</label>
            <input type="date" class="form-input" wire:model.live="date_to">
        </div>

        @if ($this->canSync)
            <button type="button" wire:click="syncPeriodAttendance" wire:loading.attr="disabled" wire:target="syncPeriodAttendance"
                wire:confirm="Tandai 'Tidak Hadir' baris yang jam masuk/pulangnya kosong (Cuti/Sakit/Izin tidak ikut berubah), dan hitung ulang jam kerja (istirahat ikut terhitung) untuk semua baris yang punya jam masuk & pulang di periode ini?"
                class="btn-ghost" title="Sync periode ini: tandai 'Tidak Hadir' kalau jam masuk/pulang kosong, hitung ulang jam kerja (istirahat ikut terhitung)">
                <i class="fa-solid fa-user-clock"></i> Sync Periode Ini
            </button>

            <button type="button" wire:click="recalculateWorkingHours" wire:loading.attr="disabled" wire:target="recalculateWorkingHours"
                wire:confirm="Hitung ulang jam kerja, telat, dan pulang cepat semua baris di periode ini sesuai jadwal masing-masing karyawan (istirahat dipotong sesuai jadwalnya)? Status kehadiran (Hadir/Terlambat) ikut diperbarui sesuai hasil hitung ulang."
                class="btn-ghost" title="Hitung ulang jam kerja, telat, dan status sesuai jadwal asli tiap karyawan (istirahat dipotong)">
                <i class="fa-solid fa-clock-rotate-left"></i> Hitung Ulang Jam Kerja & Telat
            </button>
        @endif
    @endif

    <div style="min-width:220px;flex:1;">
        <label class="form-label">Cari</label>
        <div style="position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama atau NIP..." style="padding-left:38px;">
        </div>
    </div>
</div>

@if ($mode === 'harian')
    @php $stats = $this->todayStats; @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px;">
        <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;">
            <div class="stat-card-deco" style="background:#042c6c;"></div>
            <div style="flex-shrink:0;width:42px;height:42px;background:rgba(4,44,108,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-users" style="color:#042c6c;font-size:15px;"></i>
            </div>
            <div style="min-width:0;">
                <p style="font-size:24px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $stats['total_employees'] }}</p>
                <p style="font-size:12px;font-weight:600;color:var(--text-muted);margin-top:4px;">Total Karyawan</p>
            </div>
        </div>
        @foreach ([
            ['key' => 'present', 'label' => 'Hadir', 'icon' => 'fa-check', 'style' => $statusStyle['present']],
            ['key' => 'late', 'label' => 'Terlambat', 'icon' => 'fa-clock', 'style' => $statusStyle['late']],
            ['key' => 'permit', 'label' => 'Izin', 'icon' => 'fa-file-lines', 'style' => $statusStyle['permit']],
            ['key' => 'leave', 'label' => 'Cuti', 'icon' => 'fa-umbrella-beach', 'style' => $statusStyle['leave']],
            ['key' => 'sick', 'label' => 'Sakit', 'icon' => 'fa-briefcase-medical', 'style' => $statusStyle['sick']],
        ] as $card)
            <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;">
                <div class="stat-card-deco" style="background:{{ $card['style']['color'] }};"></div>
                <div style="flex-shrink:0;width:42px;height:42px;background:{{ $card['style']['bg'] }};border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid {{ $card['icon'] }}" style="color:{{ $card['style']['color'] }};font-size:15px;"></i>
                </div>
                <div style="min-width:0;">
                    <p style="font-size:24px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $stats[$card['key']] }}</p>
                    <p style="font-size:12px;font-weight:600;color:var(--text-muted);margin-top:4px;">{{ $card['label'] }}</p>
                </div>
            </div>
        @endforeach
        <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;">
            <div class="stat-card-deco" style="background:#64748b;"></div>
            <div style="flex-shrink:0;width:42px;height:42px;background:rgba(100,116,139,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-hourglass-half" style="color:#64748b;font-size:15px;"></i>
            </div>
            <div style="min-width:0;">
                <p style="font-size:24px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $stats['not_recorded'] }}</p>
                <p style="font-size:12px;font-weight:600;color:var(--text-muted);margin-top:4px;">Belum Presensi</p>
            </div>
        </div>
    </div>

    @php $rows = $this->todayRows; @endphp
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
        <div style="overflow-x:auto;">
            <table class="data-table" style="min-width:640px;font-size:12px;">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th style="text-align:center;">Status</th>
                        <th style="text-align:center;">Jam Masuk</th>
                        <th style="text-align:center;">Jam Pulang</th>
                        <th style="text-align:center;">Terlambat</th>
                        <th style="text-align:center;">Jam Kerja</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $style = $statusStyle[$row->status] ?? ['label' => 'Belum Presensi', 'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,0.1)']; @endphp
                        <tr>
                            <td style="white-space:nowrap;">
                                <p style="font-weight:600;color:var(--text-heading);margin:0;">{{ $row->name }}</p>
                                <span style="font-size:11px;color:var(--text-muted);">{{ $row->nip }}</span>
                            </td>
                            <td style="text-align:center;">
                                <span style="display:inline-flex;align-items:center;padding:3px 10px;border-radius:99px;background:{{ $style['bg'] }};color:{{ $style['color'] }};font-size:11px;font-weight:700;">{{ $style['label'] }}</span>
                            </td>
                            <td style="text-align:center;">{{ $row->check_in_time ? \Illuminate\Support\Str::substr($row->check_in_time, 0, 5) : '–' }}</td>
                            <td style="text-align:center;">{{ $row->check_out_time ? \Illuminate\Support\Str::substr($row->check_out_time, 0, 5) : '–' }}</td>
                            <td style="text-align:center;{{ $row->late_minutes > 0 ? 'color:#b45309;font-weight:700;' : '' }}">{{ $row->late_minutes > 0 ? $row->late_minutes . ' mnt' : '–' }}</td>
                            <td style="text-align:center;">{{ $row->working_minutes > 0 ? round($row->working_minutes / 60, 1) . ' jam' : '–' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:32px;text-align:center;color:var(--text-faint);">
                                @if ($search !== '')
                                    Tidak ada karyawan yang cocok dengan pencarian "{{ $search }}".
                                @else
                                    Belum ada data karyawan.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 16px;border-top:1px solid var(--border-soft);">
            {{ $rows->links('livewire.partials.pagination') }}
        </div>
    </div>
@else
    @php $stats = $this->monthlyStats; @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:20px;">
        @foreach ([
            ['value' => $stats['total_employees'], 'label' => 'Total Karyawan', 'icon' => 'fa-users', 'color' => '#042c6c'],
            ['value' => $stats['total_present_days'], 'label' => 'Total Hari Hadir', 'icon' => 'fa-check', 'color' => '#047C54'],
            ['value' => $stats['total_late'], 'label' => 'Total Terlambat (kali)', 'icon' => 'fa-clock', 'color' => '#b45309'],
            ['value' => $stats['total_late_minutes'] . ' mnt', 'label' => 'Total Menit Terlambat', 'icon' => 'fa-hourglass-half', 'color' => '#b45309'],
            ['value' => $stats['total_working_hours'] . ' jam', 'label' => 'Total Jam Kerja', 'icon' => 'fa-briefcase', 'color' => '#0f766e'],
        ] as $card)
            <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:16px 18px;">
                <div class="stat-card-deco" style="background:{{ $card['color'] }};"></div>
                <div style="flex-shrink:0;width:42px;height:42px;background:{{ $card['color'] }}1a;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid {{ $card['icon'] }}" style="color:{{ $card['color'] }};font-size:15px;"></i>
                </div>
                <div style="min-width:0;">
                    <p style="font-size:22px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $card['value'] }}</p>
                    <p style="font-size:12px;font-weight:600;color:var(--text-muted);margin-top:4px;">{{ $card['label'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @php $rows = $this->monthlyRows; @endphp
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
        <div style="overflow-x:auto;">
            <table class="data-table" style="min-width:820px;font-size:12px;">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th style="text-align:center;">Hadir</th>
                        <th style="text-align:center;">Tidak Hadir</th>
                        <th style="text-align:center;">Terlambat</th>
                        <th style="text-align:center;">Total Menit Terlambat</th>
                        <th style="text-align:center;">Izin (jam)</th>
                        <th style="text-align:center;">Cuti (hari)</th>
                        <th style="text-align:center;">Lembur Disetujui (jam)</th>
                        <th style="text-align:center;">Total Jam Kerja</th>
                        <th style="text-align:center;">Rata-rata Jam Kerja</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:click="openDetail('{{ $row->id }}')" style="cursor:pointer;" title="Klik untuk lihat rincian absensi harian">
                            <td style="white-space:nowrap;">
                                <p style="font-weight:600;color:var(--text-heading);margin:0;">{{ $row->name }}</p>
                                <span style="font-size:11px;color:var(--text-muted);">{{ $row->nip }}</span>
                            </td>
                            <td style="text-align:center;color:#047C54;font-weight:700;">{{ $row->present_count }}</td>
                            <td style="text-align:center;color:#dc2626;">{{ $row->absent_count }}</td>
                            <td style="text-align:center;color:#b45309;">{{ $row->late_count }}</td>
                            <td style="text-align:center;">{{ $row->total_late_minutes }}</td>
                            <td style="text-align:center;">{{ $row->permission_hours }}</td>
                            <td style="text-align:center;">{{ $row->leave_days }}</td>
                            <td style="text-align:center;color:#0f766e;">{{ $row->overtime_hours }}</td>
                            <td style="text-align:center;">{{ round($row->total_working_minutes / 60, 1) }} jam</td>
                            <td style="text-align:center;">{{ $row->avg_working_hours }} jam</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="padding:32px;text-align:center;color:var(--text-faint);">
                                @if ($search !== '')
                                    Tidak ada karyawan yang cocok dengan pencarian "{{ $search }}".
                                @else
                                    Belum ada data karyawan.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 16px;border-top:1px solid var(--border-soft);">
            {{ $rows->links('livewire.partials.pagination') }}
        </div>
    </div>
@endif

@if ($showSyncModal && $this->canSync)
    <div class="modal-overlay" wire:click="closeSyncModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
                <div>
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Sync Presensi</h3>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">Sinkron ulang dari access_logs untuk 1 tanggal, cuma scan setelah jam yang dipilih.</p>
                </div>
                <button type="button" wire:click="closeSyncModal" class="btn-ghost" style="padding:6px 10px;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div style="display:flex;flex-direction:column;gap:12px;">
                <div>
                    <label class="form-label">Tanggal</label>
                    <input type="date" class="form-input" wire:model="syncDate">
                    @error('syncDate') <span style="font-size:11px;color:#dc2626;">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="form-label">Dari Jam</label>
                    <input type="time" class="form-input" wire:model="syncFromTime">
                    @error('syncFromTime') <span style="font-size:11px;color:#dc2626;">{{ $message }}</span> @enderror
                    <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">Contoh: 08:00 - scan sebelum jam ini di tanggal tersebut diabaikan.</p>
                </div>

                @if ($syncResultMessage)
                    <div style="padding:10px 12px;border-radius:8px;font-size:12px;font-weight:600;background:{{ $syncResultType === 'success' ? 'rgba(4,124,84,0.1)' : 'rgba(220,38,38,0.1)' }};color:{{ $syncResultType === 'success' ? '#047C54' : '#dc2626' }};">
                        {{ $syncResultMessage }}
                    </div>

                    @if (count($syncNewRecords) > 0)
                        <div style="max-height:220px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;">
                            <p style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin:0;">Presensi Baru (notifikasi terkirim)</p>
                            @foreach ($syncNewRecords as $new)
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;border-radius:8px;background:var(--surface-alt);">
                                    <div style="min-width:0;">
                                        <p style="font-size:12px;font-weight:600;color:var(--text-heading);margin:0;">{{ $new['name'] }}</p>
                                        <span style="font-size:10.5px;color:var(--text-muted);">{{ $new['nip'] }}</span>
                                    </div>
                                    <span style="font-size:11px;color:var(--text-muted);white-space:nowrap;">
                                        {{ $new['check_in_time'] ? \Illuminate\Support\Str::substr($new['check_in_time'], 0, 5) : '–' }}
                                        &ndash;
                                        {{ $new['check_out_time'] ? \Illuminate\Support\Str::substr($new['check_out_time'], 0, 5) : '–' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:4px;">
                    <button type="button" wire:click="closeSyncModal" class="btn-ghost">Tutup</button>
                    <button type="button" wire:click="runSync" wire:loading.attr="disabled" class="btn-primary">
                        <span wire:loading.remove wire:target="runSync"><i class="fa-solid fa-arrow-rotate-right"></i> Mulai Sync</span>
                        <span wire:loading wire:target="runSync">Memproses...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Modal rincian absensi harian — muncul saat baris karyawan (mode Periode) diklik, isinya
per-tanggal dalam periode yang lagi ditampilkan, bukan cuma angka agregat di tabel. --}}
@if ($this->selectedUser)
    <div class="modal-overlay" wire:click="closeDetail">
        <div class="modal-box modal-box-xl" onclick="event.stopPropagation()">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
                <div>
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">{{ $this->selectedUser->name }}</h3>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">
                        {{ $this->selectedUser->nip }} &middot;
                        {{ \Illuminate\Support\Carbon::parse($date_from)->translatedFormat('d M Y') }}
                        &ndash; {{ \Illuminate\Support\Carbon::parse($date_to)->translatedFormat('d M Y') }}
                    </p>
                </div>
                <button type="button" wire:click="closeDetail" class="btn-ghost" style="padding:6px 10px;"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div style="max-height:65vh;overflow-y:auto;overflow-x:auto;">
                <table class="data-table" style="min-width:720px;font-size:12px;">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Keterangan Hadir</th>
                            <th style="text-align:center;">Jam Kerja</th>
                            <th style="text-align:center;">Absen Masuk</th>
                            <th style="text-align:center;">Absen Pulang</th>
                            <th>Keterangan Cuti/Izin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $leaveOrPermissionDates = $this->selectedUserLeaveOrPermissionDates; @endphp
                        @forelse ($this->selectedUserDetailRows as $detail)
                            @php
                                $foundLeave = $leaveOrPermissionDates[$detail->date] ?? null;
                                // Reset tiap iterasi - kalau tidak, nilai baris SEBELUMNYA bisa "bocor" ke
                                // baris ini lewat cabang if(!has_record) yang tidak menyentuh variabel ini.
                                $tidakAbsenMasuk = false;
                                $tidakAbsenPulang = false;

                                if (! $detail->has_record) {
                                    // Tidak ada baris attendance_records SAMA SEKALI untuk tanggal ini - wajar
                                    // untuk Cuti/Izin (approval tidak nulis attendance_records). Ada pengajuan
                                    // -> tampilkan langsung sebagai Cuti/Izin (bukan cuma "kemungkinan", karena
                                    // memang tidak ada data presensi yang bertentangan). Tidak ada pengajuan
                                    // sama sekali -> abu-abu "Tidak Ada Data" (belum tentu absen, cuma belum
                                    // pernah ke-sync/scan).
                                    if ($foundLeave) {
                                        $style = $foundLeave['type'] === 'leave' ? $statusStyle['leave'] : $statusStyle['permit'];
                                    } else {
                                        $style = ['label' => 'Tidak Ada Data', 'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,0.1)'];
                                    }
                                    $possibleLeave = null;
                                } else {
                                    $style = $statusStyle[$detail->status] ?? ['label' => $detail->status ?? '-', 'color' => '#64748b', 'bg' => 'rgba(100,116,139,0.1)'];
                                    // Baris ADA tapi statusnya 'absent' dan TETAP ada pengajuan Cuti/Izin di
                                    // tanggal itu - kontradiksi (mestinya kalau sync jalan benar, baris begini
                                    // dikecualikan - lihat syncPeriodAttendance()), tampilkan hedged "Kemungkinan",
                                    // bukan Cuti/Izin pasti, karena ada baris presensi nyata yang bilang absent.
                                    $possibleLeave = $detail->status === \App\Enum\AttendanceStatus::ABSENT->value ? $foundLeave : null;
                                    if ($possibleLeave) {
                                        $style = ['label' => $style['label'], 'color' => '#b45309', 'bg' => 'rgba(180,83,9,0.15)'];
                                    }

                                    // "Tidak tertib" - status Hadir/Terlambat tetap benar (cuma dua kemungkinan
                                    // status kalau ada baris presensi, lihat FixedScheduleCalculator::
                                    // calculateOne()), tapi salah satu jam absen kosong - warnanya dibedakan
                                    // biar kelihatan, bukan disamakan visual dengan Hadir/Terlambat normal
                                    // yang absennya lengkap. Dua arah:
                                    // - tidak absen masuk tapi ada absen pulang (scan tunggal di jam pulang
                                    //   jadwal fixed, lihat AccessLogScheduleReader::singleScanIsCheckout()).
                                    // - ada absen masuk tapi tidak sempat/lupa absen pulang.
                                    $isPresentOrLate = in_array($detail->status, [
                                        \App\Enum\AttendanceStatus::PRESENT->value,
                                        \App\Enum\AttendanceStatus::LATE->value,
                                    ], true);
                                    $tidakAbsenMasuk = $isPresentOrLate && ! $detail->check_in_time && $detail->check_out_time;
                                    $tidakAbsenPulang = $isPresentOrLate && $detail->check_in_time && ! $detail->check_out_time;
                                    if ($tidakAbsenMasuk || $tidakAbsenPulang) {
                                        $style = ['label' => $style['label'], 'color' => '#ca8a04', 'bg' => 'rgba(202,138,4,0.12)'];
                                    }
                                }
                            @endphp
                            <tr style="background:{{ $style['bg'] }};">
                                <td style="white-space:nowrap;font-weight:600;color:var(--text-heading);">{{ \Illuminate\Support\Carbon::parse($detail->date)->translatedFormat('D, d M Y') }}</td>
                                <td style="white-space:nowrap;font-weight:700;color:{{ $style['color'] }};">
                                    {{ $style['label'] }}
                                    @if ($detail->late_minutes)
                                        <span style="font-weight:400;color:var(--text-muted);">(telat {{ $detail->late_minutes }} mnt)</span>
                                    @endif
                                    @if ($tidakAbsenMasuk ?? false)
                                        <span style="font-weight:400;color:var(--text-muted);">(tidak absen masuk)</span>
                                    @elseif ($tidakAbsenPulang ?? false)
                                        <span style="font-weight:400;color:var(--text-muted);">(tidak absen pulang)</span>
                                    @endif
                                </td>
                                <td style="text-align:center;">{{ $detail->working_minutes ? round($detail->working_minutes / 60, 1) . ' jam' : '–' }}</td>
                                <td style="text-align:center;">{{ $detail->check_in_time ? \Illuminate\Support\Str::substr($detail->check_in_time, 0, 5) : '–' }}</td>
                                <td style="text-align:center;">{{ $detail->check_out_time ? \Illuminate\Support\Str::substr($detail->check_out_time, 0, 5) : '–' }}</td>
                                <td>
                                    @if ($possibleLeave)
                                        <span style="color:#b45309;font-weight:600;"><i class="fa-solid fa-circle-question"></i> Kemungkinan {{ $possibleLeave['label'] }} ({{ $possibleLeave['status']->getLabel() }}) - ada baris presensi absent</span>
                                    @elseif ($foundLeave)
                                        <span style="color:{{ $foundLeave['type'] === 'leave' ? $statusStyle['leave']['color'] : $statusStyle['permit']['color'] }};font-weight:600;"><i class="fa-solid fa-circle-check"></i> Mengajukan {{ $foundLeave['label'] }} ({{ $foundLeave['status']->getLabel() }})</span>
                                    @else
                                        <span style="color:var(--text-faint);">–</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="padding:24px;text-align:center;color:var(--text-faint);">Tidak ada baris presensi untuk karyawan ini di periode tersebut.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
</div>
