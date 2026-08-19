<div>
    <x-page-header title="Jadwal" />

    {{-- Filter company lewat Livewire (server-side) — SEBELUMNYA pakai List.js di browser,
         tapi List.js mengunci baris tbody-nya sendiri sehingga tidak ikut ke-morph Livewire pas
         companyId ganti (baris perusahaan lama nyangkut terus), jadi dicabut. Tidak ada kotak
         cari, filter perusahaan sudah cukup. --}}
    <div class="mt-4">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;justify-content:space-between;">
            <x-company-filter :companies="$this->companies" wire:model.live="companyId" />

            @if ($this->canCreateSchedule)
                <a href="{{ route('presensi.jadwal.tambah') }}" wire:navigate class="btn-primary" style="text-decoration:none;">
                    <i class="fa-solid fa-plus"></i> Tambah Jadwal
                </a>
            @endif
        </div>

        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;margin-top:16px;{{ $this->schedules->isEmpty() ? 'display:none;' : '' }}">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="border-bottom:1.5px solid var(--border);">
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Nama Jadwal</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Tipe</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Kepala Ruangan</th>
                        <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->schedules as $schedule)
                        <tr wire:key="'schedule-'.{{ $schedule->id }}" style="border-bottom:1px solid var(--border-soft);">
                            <td style="padding:9px 14px;white-space:nowrap;">
                                @if ($schedule->color)
                                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $schedule->color }};margin-right:6px;"></span>
                                @endif
                                <span style="font-weight:600;">{{ $schedule->name }}</span>
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;">
                                @php $typeColor = $this->scheduleTypeColor($schedule->schedule_type); @endphp
                                <span style="display:inline-block;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $typeColor['bg'] }};color:{{ $typeColor['text'] }};">
                                    {{ $this->scheduleTypeLabel($schedule->schedule_type) }}
                                </span>
                            </td>
                            <td style="padding:9px 14px;white-space:nowrap;color:{{ $schedule->head ? 'var(--text-body)' : 'var(--text-faint)' }};">
                                {{ $schedule->head?->name ?? '-' }}
                            </td>
                            <td style="padding:9px 14px;text-align:left;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;">
                                    <a href="{{ route('presensi.jadwal.show', $schedule) }}" wire:navigate style="padding:5px 12px;font-size:11.5px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:5px;background:#0284c7;color:#fff;">
                                        <i class="fa-solid fa-eye"></i> Lihat
                                    </a>
                                    @can('update', $schedule)
                                        <a href="{{ route('presensi.jadwal.pengguna', $schedule) }}" wire:navigate style="padding:5px 12px;font-size:11.5px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:5px;background:#047C54;color:#fff;">
                                            <i class="fa-solid fa-users-gear"></i> Atur Pengguna
                                        </a>
                                        <a href="{{ route('presensi.jadwal.edit', $schedule) }}" wire:navigate class="btn-ghost" style="padding:5px 12px;font-size:11.5px;">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </a>
                                    @endcan
                                    @can('delete', $schedule)
                                        @unless ($this->hasAttendanceHistory($schedule))
                                            <button type="button" wire:click="deleteSchedule('{{ $schedule->id }}')" wire:confirm="Hapus jadwal &quot;{{ $schedule->name }}&quot;? Semua pengaturan hari & penempatan jabatannya ikut terhapus." class="btn-ghost" style="padding:5px 12px;font-size:11.5px;color:#dc2626;">
                                                <i class="fa-solid fa-trash"></i> Hapus
                                            </button>
                                        @endunless
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="padding:32px 16px;text-align:center;{{ $this->schedules->isEmpty() ? '' : 'display:none;' }}">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada jadwal untuk perusahaan ini.</p>
        </div>
    </div>
</div>
