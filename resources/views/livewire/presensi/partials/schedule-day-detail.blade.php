{{-- Detail tipe Fixed/Flexible — tab Jam Kerja (tabel 7 hari read-only) + tab Pengguna
     (jabatan yang di-assign lewat "Atur Pengguna Jadwal", job_position_id). Dipisah dari file
     utama schedule-detail-page.blade.php supaya tidak nyampur sama detail tipe Shift — lihat
     partials/schedule-shift-detail.blade.php. --}}
@if ($tab === 'jam-kerja')
    @php
        $zoneWork = 'rgba(4,44,108,0.08)';
        $zoneOvertime = 'transparent';
        $numStyle = 'padding:4px 3px;font-size:10.5px;text-align:center;font-variant-numeric:tabular-nums;';
        $dataColCount = $schedule->schedule_type === 'fixed' ? 9 : 6;
    @endphp

    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
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
                        @if ($schedule->schedule_type === 'fixed')
                            <th colspan="3" style="text-align:center;padding:8px 10px;white-space:nowrap;background:{{ $zoneOvertime }};">Lembur Awal <span style="font-weight:400;color:var(--text-faint);">(menit)</span></th>
                            <th colspan="3" style="text-align:center;padding:8px 10px;white-space:nowrap;background:{{ $zoneWork }};">Jam Kerja</th>
                            <th colspan="3" style="text-align:center;padding:8px 10px;white-space:nowrap;background:{{ $zoneOvertime }};">Lembur Akhir <span style="font-weight:400;color:var(--text-faint);">(menit)</span></th>
                        @else
                            <th colspan="3" style="text-align:center;padding:8px 10px;white-space:nowrap;background:{{ $zoneWork }};">Jam Kerja <span style="font-weight:400;color:var(--text-faint);">(menit)</span></th>
                            <th colspan="3" style="text-align:center;padding:8px 10px;white-space:nowrap;background:{{ $zoneOvertime }};">Lembur <span style="font-weight:400;color:var(--text-faint);">(menit)</span></th>
                        @endif
                        <th style="text-align:center;padding:8px 22px 8px 10px;white-space:nowrap;">Rincian</th>
                    </tr>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th></th>
                        <th></th>
                        @if ($schedule->schedule_type === 'fixed')
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
                        @php $isOff = $this->isDayOff($dow); $row = $days[$dow] ?? []; @endphp
                        <tr wire:key="'day-'.{{ $dow }}" style="border-bottom:1px solid var(--border-soft);{{ $isOff ? 'background:var(--danger-soft-bg);' : '' }}">
                            <td style="padding:6px 6px 6px 16px;white-space:nowrap;">
                                <span style="font-size:11.5px;font-weight:600;color:{{ $isOff ? 'var(--danger-soft-text)' : 'var(--text-heading)' }};">{{ $this->dayLabel($dow) }}</span>
                            </td>
                            <td style="padding:6px;text-align:center;">
                                <i class="fa-solid {{ $isOff ? 'fa-check' : 'fa-xmark' }}" style="color:{{ $isOff ? 'var(--danger-soft-text)' : 'var(--text-faint)' }};"></i>
                            </td>

                            @if ($isOff)
                                <td colspan="{{ $dataColCount }}" style="padding:10px;">
                                    <span style="font-size:11.5px;font-weight:600;color:var(--danger-soft-text);"><i class="fa-solid fa-mug-hot"></i> Libur — tidak ada jam kerja atau lembur.</span>
                                </td>
                            @elseif ($schedule->schedule_type === 'fixed')
                                <td style="{{ $numStyle }}background:{{ $zoneOvertime }};">{{ $row['overtime_before_min'] ?? 0 }}</td>
                                <td style="{{ $numStyle }}background:{{ $zoneOvertime }};">{{ $row['overtime_before_break'] ?? 0 }}</td>
                                <td style="{{ $numStyle }}background:{{ $zoneOvertime }};">{{ $row['overtime_before_max'] ?? 0 }}</td>

                                <td style="{{ $numStyle }}background:{{ $zoneWork }};">{{ $this->formatTime($row['start_time'] ?? null) }}</td>
                                <td style="{{ $numStyle }}background:{{ $zoneWork }};">{{ $row['break_minutes'] ?? 0 }}</td>
                                <td style="{{ $numStyle }}background:{{ $zoneWork }};">{{ $this->formatTime($row['end_time'] ?? null) }}</td>

                                <td style="{{ $numStyle }}background:{{ $zoneOvertime }};">{{ $row['overtime_after_min'] ?? 0 }}</td>
                                <td style="{{ $numStyle }}background:{{ $zoneOvertime }};">{{ $row['overtime_after_break'] ?? 0 }}</td>
                                <td style="{{ $numStyle }}background:{{ $zoneOvertime }};">{{ $row['overtime_after_max'] ?? 0 }}</td>
                            @else
                                <td style="{{ $numStyle }}background:{{ $zoneWork }};">{{ $row['break_minutes'] ?? 0 }}</td>
                                <td style="{{ $numStyle }}background:{{ $zoneWork }};">{{ $row['min_work_minutes'] ?? 0 }}</td>
                                <td style="{{ $numStyle }}background:{{ $zoneWork }};">{{ $row['max_work_minutes'] ?? 0 }}</td>

                                <td style="{{ $numStyle }}background:{{ $zoneOvertime }};">{{ $row['overtime_break_minutes'] ?? 0 }}</td>
                                <td style="{{ $numStyle }}background:{{ $zoneOvertime }};">{{ $row['overtime_min_minutes'] ?? 0 }}</td>
                                <td style="{{ $numStyle }}background:{{ $zoneOvertime }};">{{ $row['overtime_max_minutes'] ?? 0 }}</td>
                            @endif

                            <td style="padding:4px 8px 4px 4px;text-align:center;white-space:nowrap;">
                                <span class="rincian-tooltip-wrap">
                                    <button type="button" class="btn-ghost" style="padding:3px 6px;font-size:10px;">
                                        <i class="fa-solid fa-circle-info"></i>
                                    </button>
                                    <span class="rincian-tooltip">
                                        <p class="rincian-title">{{ $this->dayLabel($dow) }}</p>
                                        @if ($isOff)
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
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@elseif ($tab === 'kantor')
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;">
        <p style="font-size:11px;color:var(--text-muted);margin:0 0 4px;">Perusahaan</p>
        <p style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0 0 16px;">{{ $schedule->company?->name ?? '-' }}</p>

        <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 8px;">Jabatan yang memakai jadwal ini ({{ $this->assignments->count() }})</p>
        @forelse ($this->assignments as $assignment)
            <div wire:key="'assignment-'.{{ $assignment->id }}" style="padding:7px 0;border-bottom:1px solid var(--border-soft);font-size:12.5px;display:flex;align-items:center;gap:8px;">
                <i class="fa-solid fa-user" style="font-size:10px;color:var(--text-faint);"></i>
                <span>{{ $assignment->jobPosition?->name ?? '-' }}</span>
                <span style="margin-left:auto;font-size:10.5px;color:var(--text-faint);">sejak {{ $assignment->effective_from?->translatedFormat('d M Y') }}</span>
            </div>
        @empty
            <p style="font-size:12px;color:var(--text-faint);margin:0;">Belum ada jabatan yang di-assign ke jadwal ini.</p>
        @endforelse

        @if ($this->canEdit)
            <a href="{{ route('presensi.jadwal.pengguna', $schedule) }}" wire:navigate class="btn-ghost" style="display:inline-flex;margin-top:14px;padding:7px 14px;font-size:11.5px;">
                <i class="fa-solid fa-users-gear"></i> Atur Pengguna Jadwal
            </a>
        @endif
    </div>
@endif
