{{-- Form tipe Shift: tabel kode shift (+ tombol tambah) dan tabel karyawan perusahaan
     terpilih. Dipisah dari file utama create-schedule-page.blade.php supaya tidak nyampur
     sama form Fixed/Flexible (lihat partials/schedule-day-form.blade.php). Aktivasi Template
     Rotasi (use_template) SENGAJA tidak ada di sini — diatur langsung di tab "Template Shift"
     pada halaman Lihat Jadwal (tombol Aktifkan/Batalkan), bukan di form Create/Edit. --}}
<div style="display:flex;align-items:center;justify-content:space-between;padding:14px 22px 0;">
    <p style="font-size:11.5px;font-weight:700;color:var(--text-heading);margin:0;">Kode Shift</p>
    <button type="button" wire:click="addShiftType" class="btn-primary" style="font-size:11.5px;padding:8px 16px;">
        <i class="fa-solid fa-plus"></i> Tambah Shift
    </button>
</div>

<div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:11px;">
        <thead>
            <tr style="border-bottom:1.5px solid var(--border);">
                <th style="text-align:left;padding:10px 10px 10px 22px;white-space:nowrap;">Inisial</th>
                <th style="text-align:center;padding:10px;white-space:nowrap;">Warna</th>
                <th style="text-align:center;padding:10px;white-space:nowrap;">Warna Font</th>
                <th style="text-align:left;padding:10px;white-space:nowrap;">Nama Shift</th>
                <th style="text-align:center;padding:10px;white-space:nowrap;">Masuk</th>
                <th style="text-align:center;padding:10px;white-space:nowrap;">Istirahat (mnt)</th>
                <th style="text-align:center;padding:10px;white-space:nowrap;">Pulang</th>
                <th style="text-align:center;padding:10px 22px 10px 10px;white-space:nowrap;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($shiftTypes as $i => $row)
                {{-- wire:key wajib di sini: tanpa ini, Livewire's DOM diffing suka telat 1
                     request pas array $shiftTypes berubah ukuran (klasik penyebab bug
                     "tombol Tambah Shift butuh 2x klik kalau baris cuma 1"). --}}
                <tr wire:key="'shift-row-'.{{ $i }}" style="border-bottom:1px solid var(--border-soft);background:var(--surface-alt);">
                    <td style="padding:6px 6px 6px 16px;"><input type="text" maxlength="10" class="form-input" style="width:70px;" wire:model="shiftTypes.{{ $i }}.code" placeholder="Mis. P"></td>
                    <td style="padding:6px;text-align:center;"><input type="color" wire:model="shiftTypes.{{ $i }}.color" style="width:32px;height:28px;padding:2px;border:1px solid var(--border-soft);border-radius:6px;cursor:pointer;background:none;"></td>
                    <td style="padding:6px;text-align:center;"><input type="color" wire:model="shiftTypes.{{ $i }}.font_color" style="width:32px;height:28px;padding:2px;border:1px solid var(--border-soft);border-radius:6px;cursor:pointer;background:none;"></td>
                    <td style="padding:6px;"><input type="text" class="form-input" wire:model="shiftTypes.{{ $i }}.name" placeholder="Mis. Shift Pagi"></td>
                    <td style="padding:6px;text-align:center;"><input type="time" class="form-input" style="padding:5px 4px;font-size:11px;text-align:center;" wire:model="shiftTypes.{{ $i }}.start_time"></td>
                    <td style="padding:6px;text-align:center;"><input type="number" min="0" class="form-input" style="{{ $numStyle }}" wire:model="shiftTypes.{{ $i }}.break_minutes"></td>
                    <td style="padding:6px;text-align:center;"><input type="time" class="form-input" style="padding:5px 4px;font-size:11px;text-align:center;" wire:model="shiftTypes.{{ $i }}.end_time"></td>
                    <td style="padding:6px 22px 6px 6px;text-align:center;white-space:nowrap;">
                        <button type="button" wire:click="openAdvancedShift({{ $i }})" class="btn-ghost" style="padding:3px 6px;font-size:10px;" title="Pengaturan Lanjutan">
                            <i class="fa-solid fa-sliders"></i>
                        </button>
                        @if (count($shiftTypes) > 1)
                            <button type="button" wire:click="removeShiftType({{ $i }})" class="btn-ghost" style="padding:3px 6px;font-size:10px;color:#dc2626;" title="Hapus">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="padding:18px 22px;text-align:center;color:var(--text-faint);font-size:11.5px;">Belum ada kode shift, klik "+ Tambah Shift" di atas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="padding:22px;border-top:1px solid var(--border-soft);margin-top:8px;">
    {{-- Cari lewat Livewire (server-side), bukan List.js — tabelnya diganti total tiap ganti
         perusahaan, List.js suka nyisain state/baris lama nyangkut kalau kejadian bareng morph
         Livewire (lihat riwayat percakapan). Query beneran ke DB (bukan browser) tapi tabel ini
         kecil per perusahaan jadi tidak masalah. --}}
    <div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
            <p style="font-size:11.5px;font-weight:700;color:var(--text-heading);margin:0;">
                Karyawan {{ $this->companies->firstWhere('id', $companyId)?->name }}
            </p>
            <div style="position:relative;">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:11px;"></i>
                <input type="search" wire:model.live.debounce.300ms="employeeSearch" class="form-input" style="width:260px;padding:6px 10px 6px 30px;font-size:11.5px;" placeholder="Cari nama, NIP, jabatan, unit...">
            </div>
        </div>

        <div style="border:1px solid var(--border-soft);border-radius:10px;overflow:auto;max-height:320px;">
            <table style="width:100%;border-collapse:collapse;font-size:11.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);position:sticky;top:0;background:var(--surface);">
                        <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Nama</th>
                        <th style="text-align:left;padding:8px 10px;white-space:nowrap;">NIP</th>
                        <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Jabatan</th>
                        <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Unit</th>
                        <th style="text-align:center;padding:8px 10px;width:36px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->companyEmployees as $employee)
                        @php
                            $jobPosition = $employee->activeJobPosition?->jobPosition;
                        @endphp
                        <tr wire:key="'employee-'.{{ $employee->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:6px 10px;color:var(--text-body);">{{ $employee->name }}</td>
                            <td style="padding:6px 10px;color:var(--text-faint);">{{ $employee->nip }}</td>
                            <td style="padding:6px 10px;color:var(--text-body);">{{ $jobPosition?->name ?? '-' }}</td>
                            <td style="padding:6px 10px;color:var(--text-body);">{{ $jobPosition?->orgStructure?->name ?? '-' }}</td>
                            <td style="padding:6px 10px;text-align:center;">
                                <label class="custom-checkbox">
                                    <input type="checkbox" wire:model.live="selectedEmployeeIds" value="{{ $employee->id }}">
                                    <span class="box"><i class="fa-solid fa-check"></i></span>
                                </label>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding:14px;text-align:center;font-size:11.5px;color:var(--text-faint);">Tidak ada karyawan yang cocok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p style="font-size:11.5px;font-weight:700;color:var(--text-heading);margin:18px 0 10px;">
        Karyawan Terpilih ({{ count($selectedEmployeeIds) }})
    </p>
    <div style="border:1px solid var(--border-soft);border-radius:10px;overflow:auto;max-height:220px;">
        <table style="width:100%;border-collapse:collapse;font-size:11.5px;">
            <thead>
                <tr style="border-bottom:1.5px solid var(--border);position:sticky;top:0;background:rgba(2,132,199,0.16);">
                    <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Nama</th>
                    <th style="text-align:left;padding:8px 10px;white-space:nowrap;">NIP</th>
                    <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Jabatan</th>
                    <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Unit</th>
                    <th style="text-align:center;padding:8px 10px;width:36px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->selectedEmployees as $employee)
                    @php
                        $jobPosition = $employee->activeJobPosition?->jobPosition;
                    @endphp
                    <tr wire:key="'selected-'.{{ $employee->id }}" style="border-bottom:1px solid var(--border-soft);background:rgba(2,132,199,0.06);">
                        <td style="padding:6px 10px;color:var(--text-body);">{{ $employee->name }}</td>
                        <td style="padding:6px 10px;color:var(--text-faint);">{{ $employee->nip }}</td>
                        <td style="padding:6px 10px;color:var(--text-body);">{{ $jobPosition?->name ?? '-' }}</td>
                        <td style="padding:6px 10px;color:var(--text-body);">{{ $jobPosition?->orgStructure?->name ?? '-' }}</td>
                        <td style="padding:6px 10px;text-align:center;">
                            <button type="button" wire:click="removeSelectedEmployee('{{ $employee->id }}')" class="btn-ghost" style="padding:2px 6px;font-size:10px;color:#dc2626;" title="Hapus">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding:14px;text-align:center;font-size:11.5px;color:var(--text-faint);">Belum ada karyawan dipilih.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($advancedShiftIndex !== null)
    @php $advRow = $shiftTypes[$advancedShiftIndex] ?? []; @endphp
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click="closeAdvancedShift">
        <div style="background:var(--surface);border-radius:14px;max-width:640px;width:100%;max-height:90vh;overflow-y:auto;" onclick="event.stopPropagation()">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border-soft);">
                <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">
                    Pengaturan Lanjutan — {{ $advRow['name'] ?: 'Shift' }}
                </h3>
                <button type="button" wire:click="closeAdvancedShift" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div style="padding:18px 22px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label class="form-label">Batas Mulai Absensi Masuk (Menit)</label>
                    <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.checkin_buffer_minutes">
                </div>
                <div>
                    <label class="form-label">Batas Akhir Absensi Pulang (Menit)</label>
                    <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.checkout_buffer_minutes">
                </div>

                <div>
                    <label class="form-label">Toleransi Terlambat (Menit)</label>
                    <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.late_tolerance_minutes">
                </div>
                <div>
                    <label class="form-label">Toleransi Pulang Cepat (Menit)</label>
                    <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.early_leave_tolerance_minutes">
                </div>

                <div>
                    <label class="form-label">Lembur Awal Min / Ist / Max (Menit)</label>
                    <div style="display:flex;gap:6px;">
                        <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.overtime_before_min">
                        <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.overtime_before_break">
                        <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.overtime_before_max">
                    </div>
                </div>
                <div>
                    <label class="form-label">Lembur Akhir Min / Ist / Max (Menit)</label>
                    <div style="display:flex;gap:6px;">
                        <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.overtime_after_min">
                        <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.overtime_after_break">
                        <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.overtime_after_max">
                    </div>
                </div>

                <div>
                    <label class="form-label">Dihitung Kerja (Hari)</label>
                    <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.counted_work_days">
                </div>
                <div>
                    <label class="form-label">Maksimal Durasi Kerja (Menit)</label>
                    <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.max_work_minutes">
                </div>

                <div>
                    <label class="form-label">Setengah Hari Jika Kurang Dari (Menit)</label>
                    <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.half_day_threshold_minutes">
                </div>
                <div>
                    <label class="form-label">Tidak Hadir Jika Kurang Dari (Menit)</label>
                    <input type="number" min="0" class="form-input" wire:model="shiftTypes.{{ $advancedShiftIndex }}.absent_threshold_minutes">
                </div>

                <div style="grid-column:1 / -1;display:flex;align-items:center;gap:8px;">
                    <label class="custom-checkbox">
                        <input type="checkbox" wire:model="shiftTypes.{{ $advancedShiftIndex }}.is_overnight">
                        <span class="box"><i class="fa-solid fa-check"></i></span>
                    </label>
                    <span style="font-size:11.5px;color:var(--text-body);">Shift melewati tengah malam (jam pulang di hari berikutnya)</span>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;padding:14px 22px;border-top:1px solid var(--border-soft);">
                <button type="button" wire:click="closeAdvancedShift" class="btn-primary">Selesai</button>
            </div>
        </div>
    </div>
@endif
