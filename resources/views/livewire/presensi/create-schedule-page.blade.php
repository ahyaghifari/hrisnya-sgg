<div class="create-schedule-page">
    {{-- Sembunyikan ikon jam bawaan browser di input time — khusus halaman ini saja, scoped
         lewat class di root, bukan global (tabel jam kerja lebih rapi tanpa ikon dobel). --}}
    <style>
        .create-schedule-page input[type="time"]::-webkit-calendar-picker-indicator {
            display: none;
            -webkit-appearance: none;
        }
        .create-schedule-page input[type="time"] {
            -moz-appearance: textfield;
        }
        /* Nama Jadwal — cuma border bawah yang berubah biru pas fokus, bukan ring/outline
           bawaan browser (yang biasanya nambahin kotak biru di semua sisi). */
        .create-schedule-page .schedule-name-input {
            outline: none;
            box-shadow: none;
            transition: border-color 0.15s;
        }
        .create-schedule-page .schedule-name-input:focus {
            outline: none;
            box-shadow: none;
            border-bottom-color: #042c6c;
        }
        /* Tooltip "Rincian" — hover CSS buat show/hide, posisi (top/left) dihitung JS
           (resources/js/app.js) pakai position:fixed supaya lolos dari overflow-x:auto
           pembungkus tabel (position:absolute biasa ke-clip di situ). Tanpa roundtrip Livewire
           sama sekali (beda dari modal Pengaturan Lanjutan yang memang perlu wire:click). */
        .create-schedule-page .rincian-tooltip-wrap {
            position: relative;
            display: inline-block;
        }
        .create-schedule-page .rincian-tooltip {
            position: fixed;
            top: 0;
            left: 0;
            transform: translate(-100%, -50%);
            width: 340px;
            box-sizing: border-box;
            background: var(--surface);
            color: var(--text-body);
            font-size: 12px;
            line-height: 1.7;
            text-align: left;
            white-space: normal;
            padding: 16px 18px;
            border-radius: 10px;
            border: 1px solid var(--border-soft);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.15s ease, visibility 0.15s ease;
            z-index: 20;
            pointer-events: none;
        }
        .create-schedule-page .rincian-tooltip .rincian-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-heading);
            margin: 0 0 10px;
        }
        .create-schedule-page .rincian-tooltip .rincian-line {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            color: var(--text-body);
        }
        .create-schedule-page .rincian-tooltip .rincian-line > span {
            flex: 1;
            min-width: 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .create-schedule-page .rincian-tooltip .rincian-line i {
            color: var(--text-faint);
            font-size: 11px;
            margin-top: 3px;
            width: 12px;
            flex-shrink: 0;
        }
        .create-schedule-page .rincian-tooltip hr {
            border: none;
            border-top: 1px solid var(--border-soft);
            margin: 10px 0;
        }
        .create-schedule-page .rincian-tooltip ul {
            margin: 0;
            padding-left: 16px;
        }
        .create-schedule-page .rincian-tooltip-wrap:hover .rincian-tooltip {
            opacity: 1;
            visibility: visible;
        }
    </style>

    <x-page-header title="Tambah Jadwal" back="{{ route('presensi.jadwal') }}" />

    @php
        $typeMeta = [
            'flexible' => ['icon' => 'jadwal-flexible.png', 'desc' => 'Karyawan boleh datang & pulang kapan pun, yang dihitung total jam kerjanya per hari.'],
            'fixed' => ['icon' => 'jadwal-fixed.png', 'desc' => 'Jam masuk & pulang sama setiap hari kerja, cocok untuk jadwal kantor normal.'],
            'shift' => ['icon' => 'jadwal-shift.png', 'desc' => 'Rotasi kerja (Pagi/Siang/Malam, dst) yang dijadwalkan per periode, biasanya bulanan.'],
        ];
    @endphp

    @if ($step === 1)
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:36px 28px;">
            <p style="text-align:center;font-size:14px;color:var(--text-muted);margin:0 0 30px;">
                Pilih jenis jadwal kerja yang ingin diberlakukan, tentukan detailnya di langkah berikutnya.
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:28px;">
                @foreach ($this->scheduleTypes as $type)
                    @php $isSelected = $scheduleType === $type->value; @endphp
                    <button type="button" wire:click="selectType('{{ $type->value }}')"
                        style="background:none;border:none;padding:0;cursor:pointer;text-align:center;font-family:inherit;">
                        <div style="width:100%;max-width:260px;aspect-ratio:1;margin:0 auto 18px;border-radius:28px;border:2px solid {{ $isSelected ? '#042c6c' : 'var(--border)' }};background:{{ $isSelected ? 'rgba(4,44,108,0.06)' : 'var(--surface)' }};display:flex;align-items:center;justify-content:center;transition:all 0.15s;">
                            <img src="{{ asset('assets/' . $typeMeta[$type->value]['icon']) }}" alt="{{ $type->getLabel() }}" style="width:75%;height:75%;object-fit:contain;">
                        </div>

                        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:10px;">
                            <span style="width:18px;height:18px;border-radius:50%;border:2px solid {{ $isSelected ? '#042c6c' : 'var(--border)' }};display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                                @if ($isSelected)
                                    <span style="width:9px;height:9px;border-radius:50%;background:#042c6c;"></span>
                                @endif
                            </span>
                            <span style="font-size:15px;font-weight:700;color:var(--text-heading);">{{ $type->getLabel() }}</span>
                        </div>

                        <p style="font-size:12.5px;color:var(--text-muted);margin:0;line-height:1.6;">{{ $typeMeta[$type->value]['desc'] }}</p>
                    </button>
                @endforeach
            </div>

            @error('scheduleType') <p style="font-size:11.5px;color:#dc2626;text-align:center;margin-top:16px;">{{ $message }}</p> @enderror

            <div style="display:flex;justify-content:flex-end;margin-top:26px;border-top:1px solid var(--border-soft);padding-top:18px;">
                <button type="button" wire:click="nextStep" class="btn-primary">
                    Selanjutnya <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>
    @else
        @php
            $zoneWork = 'rgba(4,44,108,0.08)';
            $zoneOvertime = 'transparent';
            $numStyle = 'padding:4px 3px;font-size:10.5px;text-align:center;font-variant-numeric:tabular-nums;';
            $dataColCount = $scheduleType === 'fixed' ? 9 : 6;
        @endphp

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
            {{-- Header: identitas tipe + judul jadwal, bukan form biasa (nama jadwal itu "judul dokumen"-nya) --}}
            <div style="padding:20px 22px;border-bottom:1px solid var(--border-soft);">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:38px;height:38px;border-radius:10px;background:rgba(4,44,108,0.06);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <img src="{{ asset('assets/' . $typeMeta[$scheduleType]['icon']) }}" alt="" style="width:26px;height:26px;object-fit:contain;">
                        </div>
                        <div>
                            <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0;">{{ \App\Enum\ScheduleType::from($scheduleType)->getLabel() }}</p>
                            <button type="button" wire:click="backStep" style="background:none;border:none;padding:0;font-size:10.5px;color:var(--brand);cursor:pointer;font-family:inherit;">Ganti tipe jadwal</button>
                        </div>
                    </div>
                    <span style="font-size:10.5px;font-weight:700;letter-spacing:0.03em;color:var(--text-faint);white-space:nowrap;">LANGKAH 2/2</span>
                </div>

                <div style="display:grid;grid-template-columns:{{ $scheduleType === 'shift' ? '1fr' : '2fr 1fr' }};gap:14px;">
                    <div>
                        <label class="form-label">Nama Jadwal</label>
                        <input type="text" wire:model="name" class="schedule-name-input"
                            style="width:100%;border:none;border-bottom:2px solid var(--border);background:none;font-size:19px;font-weight:700;color:var(--text-heading);padding:4px 2px 8px;font-family:inherit;"
                            placeholder="Mis. Jam Kerja Normal">
                        @error('name') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Perusahaan</label>
                        <select class="form-input" wire:model="companyId">
                            @foreach ($this->companies as $company)
                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                            @endforeach
                        </select>
                        @error('companyId') <p style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($scheduleType === 'flexible')
                    <div style="display:flex;align-items:center;gap:10px;margin-top:14px;padding:10px 12px;background:var(--surface-alt);border-radius:10px;">
                        <i class="fa-regular fa-clock" style="color:var(--text-faint);font-size:13px;"></i>
                        <span style="font-size:11.5px;color:var(--text-muted);white-space:nowrap;">Jendela absensi:</span>
                        <input type="time" class="form-input" style="padding:6px 10px;font-size:12px;width:auto;" wire:model="flexibleCheckinFrom">
                        <span style="color:var(--text-faint);">&ndash;</span>
                        <input type="time" class="form-input" style="padding:6px 10px;font-size:12px;width:auto;" wire:model="flexibleCheckinUntil">
                        <span style="font-size:10.5px;color:var(--text-faint);">berlaku semua hari kerja</span>
                    </div>
                @endif
            </div>

            @if ($scheduleType === 'shift')
                <div style="padding:18px 22px;">
                    <p style="font-size:11.5px;color:var(--text-faint);margin:0;">
                        <i class="fa-solid fa-circle-info"></i> Isi kode shift (Pagi/Siang/Malam, dst) bisa ditambahkan setelah jadwal ini dibuat.
                    </p>
                </div>
            @else
                {{-- Tabel 7 hari, niru CONTOH JADWAL FIXED/FLEXIBEL.png di knowledge/absensi. Minggu
                     default Libur. Hari-off merangkum sel datanya jadi 1 strip "Libur", bukan
                     sederet kotak abu-abu kosong. --}}
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
                                <tr style="border-bottom:1px solid var(--border-soft);">
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
            @endif

            <div style="display:flex;justify-content:space-between;gap:8px;padding:16px 22px;border-top:1px solid var(--border-soft);background:var(--surface-alt);">
                <button type="button" wire:click="backStep" class="btn-ghost"><i class="fa-solid fa-arrow-left"></i> Kembali</button>
                <button type="button" wire:click="save" class="btn-primary">Simpan Jadwal</button>
            </div>
        </div>

        @if ($advancedDay !== null)
            @php
                $advRow = $days[$advancedDay] ?? [];
                $maxField = $this->maxDurationField();
            @endphp
            <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click="closeAdvanced">
                <div style="background:var(--surface);border-radius:14px;max-width:760px;width:100%;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border-soft);">
                        <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">
                            Pengaturan Lanjutan — {{ $this->dayLabel($advancedDay) }}
                        </h3>
                        <button type="button" wire:click="closeAdvanced" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div style="display:grid;grid-template-columns:220px 1fr;gap:0;">
                        {{-- Ringkasan, niru panel krem di KONFIGURASI JADWAL FIXED/FLEXIBLE.png --}}
                        <div style="background:var(--warn-soft-bg);padding:18px;font-size:11px;color:var(--warn-soft-text);line-height:1.9;">
                            <p style="font-weight:700;margin:0 0 8px;">{{ $this->dayLabel($advancedDay) }}</p>
                            <p style="margin:0;"><i class="fa-solid fa-tower-broadcast" style="width:14px;"></i> Batas mulai bisa scan masuk <strong>{{ $advRow['checkin_buffer_minutes'] ?? 0 }} menit</strong> sebelum jadwal</p>
                            <p style="margin:0;"><i class="fa-solid fa-tower-broadcast" style="width:14px;"></i> Batas akhir bisa scan pulang <strong>{{ $advRow['checkout_buffer_minutes'] ?? 0 }} menit</strong> sesudah jadwal</p>
                            <hr style="border:none;border-top:1px solid rgba(0,0,0,0.08);margin:10px 0;">
                            <p style="margin:0;">&bull; Dihitung kerja: <strong>{{ $advRow['counted_work_days'] ?? 1 }}</strong></p>
                            <p style="margin:0;">&bull; Maksimal durasi kerja: <strong>{{ $advRow[$maxField] ?? 0 }} menit</strong></p>
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
                                <input type="number" min="0" class="form-input" wire:model="days.{{ $advancedDay }}.{{ $maxField }}">
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
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;padding:14px 22px;border-top:1px solid var(--border-soft);">
                        <button type="button" wire:click="closeAdvanced" class="btn-primary">Selesai</button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
