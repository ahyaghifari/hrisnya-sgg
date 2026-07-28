{{-- Form tipe Fixed/Flexible: tabel 7 hari, niru CONTOH JADWAL FIXED/FLEXIBEL.png di
     knowledge/absensi. Minggu default Libur. Hari-off merangkum sel datanya jadi 1 strip
     "Libur", bukan sederet kotak abu-abu kosong. Fixed & Flexible berbagi 1 partial (bukan
     dipecah lagi) karena strukturnya sama persis, cuma beda kolom/nilai lewat kondisional
     $scheduleType di bawah — lihat partials/schedule-shift-form.blade.php buat tipe Shift. --}}
<div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:11px;table-layout:fixed;">
        <colgroup>
            <col style="width:90px;">
            <col style="width:60px;">
            @for ($i = 0; $i < $dataColCount; $i++)
                <col>
            @endfor
            <col style="width:70px;">
        </colgroup>
        <thead>
            <tr style="border-bottom:1.5px solid var(--border);">
                <th style="text-align:left;padding:10px 10px 10px 22px;white-space:nowrap;">Hari</th>
                <th style="text-align:center;padding:10px;white-space:nowrap;">Libur</th>
                @if ($scheduleType === 'fixed')
                    <th colspan="3" style="text-align:center;padding:8px 10px;white-space:nowrap;background:{{ $zoneOvertime }};">Lembur Awal <span style="font-weight:400;color:var(--text-faint);">(menit)</span></th>
                    <th colspan="3" style="text-align:center;padding:8px 10px;white-space:nowrap;background:{{ $zoneWork }};">Jam Kerja</th>
                    <th colspan="3" style="text-align:center;padding:8px 10px;white-space:nowrap;background:{{ $zoneOvertime }};">Lembur Akhir <span style="font-weight:400;color:var(--text-faint);">(menit)</span></th>
                @else
                    <th colspan="3" style="text-align:center;padding:8px 10px;white-space:nowrap;background:{{ $zoneWork }};">Jam Kerja <span style="font-weight:400;color:var(--text-faint);">(menit)</span></th>
                    <th colspan="3" style="text-align:center;padding:8px 10px;white-space:nowrap;background:{{ $zoneOvertime }};">Lembur <span style="font-weight:400;color:var(--text-faint);">(menit)</span></th>
                @endif
                <th style="text-align:center;padding:8px 22px 8px 10px;white-space:nowrap;">Aksi</th>
            </tr>
            <tr style="border-bottom:1.5px solid var(--border);">
                <th></th>
                <th></th>
                @if ($scheduleType === 'fixed')
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneOvertime }};">Min</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneOvertime }};">Ist</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneOvertime }};">Max</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneWork }};">Masuk</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneWork }};">Istirahat</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneWork }};">Pulang</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneOvertime }};">Min</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneOvertime }};">Ist</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneOvertime }};">Max</th>
                @else
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneWork }};">Ist</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneWork }};">Min</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneWork }};">Max</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneOvertime }};">Ist</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneOvertime }};">Min</th>
                    <th style="text-align:center;padding:4px 8px;font-weight:500;color:var(--text-faint);background:{{ $zoneOvertime }};">Max</th>
                @endif
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->displayDays as $dow)
                @php $isOff = $days[$dow]['is_off_day'] ?? false; @endphp
                <tr wire:key="'day-'.{{ $dow }}" style="border-bottom:1px solid var(--border-soft);">
                    <td style="padding:6px 6px 6px 16px;white-space:nowrap;">
                        <span style="font-size:11.5px;font-weight:600;color:{{ $isOff ? 'var(--danger-soft-text)' : 'var(--text-heading)' }};">{{ $this->dayLabel($dow) }}</span>
                    </td>
                    <td style="padding:6px;text-align:center;">
                        <label class="custom-checkbox" style="--accent:#dc2626;">
                            <input type="checkbox" wire:model.live="days.{{ $dow }}.is_off_day">
                            <span class="box"><i class="fa-solid fa-check"></i></span>
                        </label>
                    </td>

                    @if ($isOff)
                        <td colspan="{{ $dataColCount }}" style="padding:10px;background:var(--danger-soft-bg);">
                            <span style="font-size:11.5px;font-weight:600;color:var(--danger-soft-text);"><i class="fa-solid fa-mug-hot"></i> Libur — tidak ada jam kerja atau lembur.</span>
                        </td>
                    @elseif ($scheduleType === 'fixed')
                        <td style="padding:4px 3px;background:{{ $zoneOvertime }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.overtime_before_min"></td>
                        <td style="padding:4px 3px;background:{{ $zoneOvertime }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.overtime_before_break"></td>
                        <td style="padding:4px 3px;background:{{ $zoneOvertime }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.overtime_before_max"></td>

                        <td style="padding:4px 3px;background:{{ $zoneWork }};"><input type="time" class="form-input" style="padding:5px 4px;font-size:11px;text-align:center;font-variant-numeric:tabular-nums;" wire:model="days.{{ $dow }}.start_time"></td>
                        <td style="padding:4px 3px;background:{{ $zoneWork }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.break_minutes"></td>
                        <td style="padding:4px 3px;background:{{ $zoneWork }};"><input type="time" class="form-input" style="padding:5px 4px;font-size:11px;text-align:center;font-variant-numeric:tabular-nums;" wire:model="days.{{ $dow }}.end_time"></td>

                        <td style="padding:4px 3px;background:{{ $zoneOvertime }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.overtime_after_min"></td>
                        <td style="padding:4px 3px;background:{{ $zoneOvertime }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.overtime_after_break"></td>
                        <td style="padding:4px 3px;background:{{ $zoneOvertime }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.overtime_after_max"></td>
                    @else
                        <td style="padding:4px 3px;background:{{ $zoneWork }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.break_minutes"></td>
                        <td style="padding:4px 3px;background:{{ $zoneWork }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.min_work_minutes"></td>
                        <td style="padding:4px 3px;background:{{ $zoneWork }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.max_work_minutes"></td>

                        <td style="padding:4px 3px;background:{{ $zoneOvertime }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.overtime_break_minutes"></td>
                        <td style="padding:4px 3px;background:{{ $zoneOvertime }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.overtime_min_minutes"></td>
                        <td style="padding:4px 3px;background:{{ $zoneOvertime }};"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="days.{{ $dow }}.overtime_max_minutes"></td>
                    @endif

                    <td style="padding:4px 8px 4px 4px;text-align:center;white-space:nowrap;">
                        <span class="rincian-tooltip-wrap">
                            <button type="button" class="btn-ghost" style="padding:3px 6px;font-size:10px;" title="">
                                <i class="fa-solid fa-circle-info"></i>
                            </button>
                            <span class="rincian-tooltip">
                                <p class="rincian-title">{{ $this->dayLabel($dow) }}</p>
                                @if ($this->isDayOff($dow))
                                    <p style="margin:0;color:var(--text-muted);">Hari ini libur — tidak ada jam kerja atau lembur yang dihitung.</p>
                                @else
                                    @foreach ($this->dayDetailLines($dow) as $line)
                                        <p class="rincian-line"><i class="fa-solid {{ $line['icon'] }}"></i> <span>{!! $line['html'] !!}</span></p>
                                    @endforeach
                                    <hr>
                                    <ul>
                                        @foreach ($this->dayDetailBullets($dow) as $bullet)
                                            <li>{!! $bullet !!}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </span>
                        </span>
                        <button type="button" wire:click="openAdvanced({{ $dow }})" class="btn-ghost" style="padding:3px 6px;font-size:10px;" title="Pengaturan Lanjutan">
                            <i class="fa-solid fa-sliders"></i>
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<p style="font-size:10.5px;color:var(--text-faint);margin:0;padding:14px 22px;">
    Klik <i class="fa-solid fa-sliders"></i> di kolom Aksi buat atur toleransi, buffer absensi, dan ambang kehadiran per hari — nilai default sudah wajar dipakai langsung kalau tidak diubah.
</p>

@if ($advancedDay !== null)
    @php
        $advRow = $days[$advancedDay] ?? [];
    @endphp
    <div class="modal-overlay" wire:click="closeAdvanced">
        <div class="modal-box" style="padding:0;max-width:760px;" onclick="event.stopPropagation()">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border-soft);">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">
                    Pengaturan Lanjutan — {{ $this->dayLabel($advancedDay) }}
                </h3>
                <button type="button" wire:click="closeAdvanced" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            @if ($scheduleType === 'flexible')
                {{-- Disederhanakan jadi 2 field saja (migration 2026_07_28_190000) - 6 field lama
                     (buffer/toleransi/cap/alpha) sudah tidak relevan buat Flexible & kolomnya
                     sudah dihapus dari tabel. Batas jam masuk pindah ke late_checkin_deadline. --}}
                <div style="display:grid;grid-template-columns:220px 1fr;gap:0;">
                    <div style="background:var(--warn-soft-bg);padding:18px;font-size:11px;color:var(--warn-soft-text);line-height:1.9;">
                        <p style="font-weight:700;margin:0 0 8px;">{{ $this->dayLabel($advancedDay) }}</p>
                        <p style="margin:0;">&bull; Dihitung kerja: <strong>{{ $advRow['counted_work_days'] ?? 1 }}</strong></p>
                        <p style="margin:0;">&bull; Setengah hari jika kurang dari: <strong>{{ $advRow['half_day_threshold_minutes'] ?? 0 }} menit</strong></p>
                        <p style="margin:0;">&bull; Batas jam masuk: <strong>{{ $advRow['late_checkin_deadline'] ?? '-' }}</strong></p>
                    </div>

                    <div style="padding:18px 22px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="form-label">Dihitung Kerja (Hari)</label>
                            <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.counted_work_days">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Contoh: diisi 2, absensi hari ini terhitung 2 hari kerja.</p>
                        </div>
                        <div>
                            <label class="form-label">Setengah Hari Jika Kurang Dari (Menit)</label>
                            <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.half_day_threshold_minutes">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Durasi kerja di bawah ini terhitung setengah hari.</p>
                        </div>
                        <div>
                            <label class="form-label">Batas Jam Masuk</label>
                            <input type="time" class="form-input" wire:model="days.{{ $advancedDay }}.late_checkin_deadline">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Check-in lewat dari jam ini dianggap telat. Kosongkan kalau tidak ada batas.</p>
                        </div>
                    </div>
                </div>
            @else
                <div style="display:grid;grid-template-columns:220px 1fr;gap:0;">
                    {{-- Ringkasan, niru panel krem di KONFIGURASI JADWAL FIXED/FLEXIBLE.png --}}
                    <div style="background:var(--warn-soft-bg);padding:18px;font-size:11px;color:var(--warn-soft-text);line-height:1.9;">
                        <p style="font-weight:700;margin:0 0 8px;">{{ $this->dayLabel($advancedDay) }}</p>
                        <p style="margin:0;"><i class="fa-solid fa-tower-broadcast" style="width:14px;"></i> Batas mulai bisa scan masuk <strong>{{ $advRow['checkin_buffer_minutes'] ?? 0 }} menit</strong> sebelum jadwal</p>
                        <p style="margin:0;"><i class="fa-solid fa-tower-broadcast" style="width:14px;"></i> Batas akhir bisa scan pulang <strong>{{ $advRow['checkout_buffer_minutes'] ?? 0 }} menit</strong> sesudah jadwal</p>
                        <hr style="border:none;border-top:1px solid rgba(0,0,0,0.08);margin:10px 0;">
                        <p style="margin:0;">&bull; Dihitung kerja: <strong>{{ $advRow['counted_work_days'] ?? 1 }}</strong></p>
                        <p style="margin:0;">&bull; Maksimal durasi kerja: <strong>{{ $advRow['max_work_minutes'] ?? 0 }} menit</strong></p>
                        <p style="margin:0;">&bull; Setengah hari jika kurang dari: <strong>{{ $advRow['half_day_threshold_minutes'] ?? 0 }} menit</strong></p>
                        <p style="margin:0;">&bull; Tidak hadir jika kurang dari: <strong>{{ $advRow['absent_threshold_minutes'] ?? 0 }} menit</strong></p>
                    </div>

                    <div style="padding:18px 22px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="form-label">Batas Mulai Absensi Masuk (Menit)</label>
                            <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.checkin_buffer_minutes">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Batas waktu absensi awal sebelum jadwal masuk kerja.</p>
                        </div>
                        <div>
                            <label class="form-label">Batas Akhir Absensi Pulang (Menit)</label>
                            <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.checkout_buffer_minutes">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Batas waktu absensi akhir sesudah jadwal pulang kerja.</p>
                        </div>

                        <div>
                            <label class="form-label">Toleransi Terlambat (Menit)</label>
                            <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.late_tolerance_minutes">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Batas toleransi keterlambatan sesudah jam masuk kerja.</p>
                        </div>
                        <div>
                            <label class="form-label">Toleransi Pulang Cepat (Menit)</label>
                            <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.early_leave_tolerance_minutes">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Batas toleransi pulang cepat sebelum jadwal pulang kerja.</p>
                        </div>

                        <div>
                            <label class="form-label">Dihitung Kerja (Hari)</label>
                            <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.counted_work_days">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Contoh: diisi 2, absensi hari ini terhitung 2 hari kerja.</p>
                        </div>
                        <div>
                            <label class="form-label">Maksimal Durasi Kerja (Menit)</label>
                            <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.max_work_minutes">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Durasi kerja di atas ini dipotong ke nilai ini.</p>
                        </div>

                        <div>
                            <label class="form-label">Setengah Hari Jika Kurang Dari (Menit)</label>
                            <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.half_day_threshold_minutes">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Durasi kerja di bawah ini terhitung setengah hari.</p>
                        </div>
                        <div>
                            <label class="form-label">Tidak Hadir Jika Kurang Dari (Menit)</label>
                            <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.absent_threshold_minutes">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Durasi kerja di bawah ini terhitung 0 hari kerja (alpha).</p>
                        </div>
                        <div>
                            <label class="form-label">Minimal Durasi Kerja (Menit)</label>
                            <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.min_work_minutes">
                            <p style="font-size:10px;color:var(--text-faint);margin-top:3px;">Scan pulang di bawah durasi ini DITOLAK (bukan cuma ditandai) - jam pulang tidak tersimpan sampai ada scan berikutnya yang cukup. Isi 0 buat nonaktifkan.</p>
                        </div>
                    </div>
                </div>
            @endif

            <div style="display:flex;justify-content:flex-end;padding:14px 22px;border-top:1px solid var(--border-soft);">
                <button type="button" wire:click="closeAdvanced" class="btn-primary">Selesai</button>
            </div>
        </div>
    </div>
@endif
