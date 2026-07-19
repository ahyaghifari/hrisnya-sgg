{{-- Detail tipe Shift — tab Jam Kerja (tabel kode shift read-only) + tab Pengguna (karyawan
     yang dicentang di form Create/Edit, user_id — BEDA dari tipe Fixed/Flexible yang pakai
     job_position_id lewat "Atur Pengguna Jadwal"). Dipisah dari file utama
     schedule-detail-page.blade.php, pasangannya: partials/schedule-day-detail.blade.php. --}}
@if ($tab === 'jam-kerja')
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 10px 10px 22px;white-space:nowrap;">Inisial</th>
                        <th style="text-align:left;padding:10px;white-space:nowrap;">Nama Shift</th>
                        <th style="text-align:center;padding:10px;white-space:nowrap;">Masuk</th>
                        <th style="text-align:center;padding:10px;white-space:nowrap;">Istirahat (mnt)</th>
                        <th style="text-align:center;padding:10px;white-space:nowrap;">Pulang</th>
                        <th style="text-align:center;padding:10px 22px 10px 10px;white-space:nowrap;">Lewat Tengah Malam</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->shiftTypes as $shiftType)
                        {{-- Warna persis sesuai isian form — solid, tidak dicampur/opacity-kan, tidak
                             ikut tema gelap/terang (warna + warna font dipilih berpasangan). --}}
                        <tr wire:key="'shift-'.{{ $shiftType->id }}" style="border-bottom:1px solid var(--border-soft);{{ $shiftType->color ? 'background:'.$shiftType->color.';' : '' }}{{ $shiftType->font_color ? 'color:'.$shiftType->font_color.';' : '' }}">
                            <td style="padding:9px 10px 9px 22px;font-weight:600;">{{ $shiftType->code }}</td>
                            <td style="padding:9px 10px;">{{ $shiftType->name }}</td>
                            <td style="padding:9px 10px;text-align:center;">{{ $this->formatTime($shiftType->start_time) }}</td>
                            <td style="padding:9px 10px;text-align:center;">{{ $shiftType->break_minutes }}</td>
                            <td style="padding:9px 10px;text-align:center;">{{ $this->formatTime($shiftType->end_time) }}</td>
                            <td style="padding:9px 22px 9px 10px;text-align:center;">
                                <i class="fa-solid {{ $shiftType->is_overnight ? 'fa-check' : 'fa-xmark' }}" style="color:{{ $shiftType->is_overnight ? '#047C54' : 'var(--text-faint)' }};"></i>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada kode shift untuk jadwal ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@elseif ($tab === 'kantor')
    <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:20px;">
        <p style="font-size:11px;color:var(--text-muted);margin:0 0 4px;">Perusahaan</p>
        <p style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0 0 16px;">{{ $schedule->company?->name ?? '-' }}</p>

        <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin:0 0 10px;">Karyawan yang memakai jadwal ini ({{ $this->shiftEmployees->count() }})</p>

        {{-- Format tabel sama seperti "Karyawan Terpilih" di form Create/Edit — lihat
             partials/schedule-shift-form.blade.php. --}}
        <div style="border:1px solid var(--border-soft);border-radius:10px;overflow:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:11.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);background:rgba(2,132,199,0.16);">
                        <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Nama</th>
                        <th style="text-align:left;padding:8px 10px;white-space:nowrap;">NIP</th>
                        <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Jabatan</th>
                        <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Unit</th>
                        <th style="text-align:left;padding:8px 10px;white-space:nowrap;">Sejak</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->shiftEmployees as $assignment)
                        @php $jobPosition = $assignment->user?->activeJobPosition?->jobPosition; @endphp
                        <tr wire:key="'shift-emp-'.{{ $assignment->id }}" style="border-bottom:1px solid var(--border-soft);background:rgba(2,132,199,0.06);">
                            <td style="padding:8px 10px;color:var(--text-body);">{{ $assignment->user?->name ?? '-' }}</td>
                            <td style="padding:8px 10px;color:var(--text-faint);">{{ $assignment->user?->nip ?? '-' }}</td>
                            <td style="padding:8px 10px;color:var(--text-body);">{{ $jobPosition?->name ?? '-' }}</td>
                            <td style="padding:8px 10px;color:var(--text-body);">{{ $jobPosition?->orgStructure?->name ?? '-' }}</td>
                            <td style="padding:8px 10px;color:var(--text-faint);white-space:nowrap;">{{ $assignment->effective_from?->translatedFormat('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding:24px;text-align:center;color:var(--text-faint);">Belum ada karyawan yang dipilih untuk jadwal ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <a href="{{ route('presensi.jadwal.edit', $schedule) }}" wire:navigate class="btn-ghost" style="display:inline-flex;margin-top:14px;padding:7px 14px;font-size:11.5px;">
            <i class="fa-solid fa-pen"></i> Ubah Karyawan Terpilih
        </a>
    </div>
@endif
