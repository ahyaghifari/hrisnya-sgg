<div class="create-schedule-page">
    {{-- Tooltip "Rincian" — sama persis pola CreateSchedulePage (hover CSS, posisi dihitung JS
         resources/js/app.js, position:fixed biar lolos overflow-x:auto pembungkus tabel). --}}
    <style>
        .create-schedule-page .rincian-tooltip-wrap { position: relative; display: inline-block; }
        .create-schedule-page .rincian-tooltip {
            position: fixed; top: 0; left: 0; transform: translate(-100%, -50%); width: 340px;
            box-sizing: border-box; background: var(--surface); color: var(--text-body);
            font-size: 12px; line-height: 1.7; text-align: left; white-space: normal;
            padding: 16px 18px; border-radius: 10px; border: 1px solid var(--border-soft);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); opacity: 0; visibility: hidden;
            transition: opacity 0.15s ease, visibility 0.15s ease; z-index: 20; pointer-events: none;
        }
        .create-schedule-page .rincian-tooltip .rincian-title { font-size: 14px; font-weight: 700; color: var(--text-heading); margin: 0 0 10px; }
        .create-schedule-page .rincian-tooltip .rincian-line { display: flex; gap: 8px; align-items: flex-start; color: var(--text-body); }
        .create-schedule-page .rincian-tooltip .rincian-line > span { flex: 1; min-width: 0; word-wrap: break-word; overflow-wrap: break-word; }
        .create-schedule-page .rincian-tooltip .rincian-line i { color: var(--text-faint); font-size: 11px; margin-top: 3px; width: 12px; flex-shrink: 0; }
        .create-schedule-page .rincian-tooltip hr { border: none; border-top: 1px solid var(--border-soft); margin: 10px 0; }
        .create-schedule-page .rincian-tooltip ul { margin: 0; padding-left: 16px; }
        .create-schedule-page .rincian-tooltip-wrap:hover .rincian-tooltip { opacity: 1; visibility: visible; }

        .schedule-detail-tab { padding: 10px 4px; margin-right: 22px; font-size: 13px; font-weight: 600; color: var(--text-faint); background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer; font-family: inherit; }
        .schedule-detail-tab.is-active { color: #042c6c; border-bottom-color: #042c6c; }
        html.dark .schedule-detail-tab.is-active { color: #6ea8e0; border-bottom-color: #6ea8e0; }

        /* Indikator loading halaman: garis progress di atas layar + pill "Memuat data..." di tengah atas. */
        .schedule-loading-bar { position: fixed; top: 0; left: 0; right: 0; height: 3px; z-index: 9998; overflow: hidden; background: rgba(4,44,108,0.12); }
        .schedule-loading-bar::after { content: ''; position: absolute; top: 0; bottom: 0; width: 40%; background: linear-gradient(90deg, #042c6c, #6ea8e0); border-radius: 99px; animation: schedule-loading-slide 1.1s ease-in-out infinite; }
        @keyframes schedule-loading-slide { 0% { left: -40%; } 100% { left: 100%; } }
        .schedule-loading-pill { position: fixed; top: 76px; left: 50%; transform: translateX(-50%); z-index: 9998; display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 99px; background: var(--surface); color: var(--text-heading); border: 1px solid var(--border); box-shadow: 0 8px 24px rgba(15,23,42,0.18); font-size: 12.5px; font-weight: 700; }
        .schedule-loading-pill i { color: #042c6c; }
        html.dark .schedule-loading-pill i { color: #6ea8e0; }
        @media (prefers-reduced-motion: reduce) { .schedule-loading-bar::after { animation-duration: 2.4s; } }
    </style>
    {{-- Indikator loading tunggal untuk seluruh halaman: aktif selama ada request Livewire yang
         jalan dari halaman ini ATAU tab-tab anak-nya (komponen nested punya request sendiri, jadi
         wire:loading di induk saja tidak cukup). Muncul setelah 150ms biar tidak berkedip di request
         cepat. Hook di-lepas saat komponen dibongkar (wire:navigate) supaya tidak menumpuk. --}}
    <div
        x-data="{
            pending: 0,
            show: false,
            timer: null,
            off: null,
            init() {
                this.off = Livewire.hook('commit', ({ component, respond, fail }) => {
                    if (! component.name.startsWith('presensi.schedule')) return;
                    let done = false;
                    const end = () => { if (done) return; done = true; this.end(); };
                    this.begin();
                    respond(end);
                    fail(end);
                });
            },
            begin() {
                this.pending++;
                if (! this.timer && ! this.show) {
                    this.timer = setTimeout(() => { this.timer = null; if (this.pending > 0) this.show = true; }, 150);
                }
            },
            end() {
                this.pending = Math.max(0, this.pending - 1);
                if (this.pending === 0) { clearTimeout(this.timer); this.timer = null; this.show = false; }
            },
            destroy() { if (this.off) this.off(); clearTimeout(this.timer); },
        }"
    >
        <div x-show="show" x-cloak x-transition.opacity.duration.150ms class="schedule-loading-bar" aria-hidden="true"></div>
        <div x-show="show" x-cloak x-transition.opacity.duration.150ms class="schedule-loading-pill" role="status" aria-live="polite">
            <i class="fa-solid fa-circle-notch fa-spin"></i> Memuat data...
        </div>
    </div>


    {{-- :title (bukan title="{{ }}") - "{{ }}" di sini escape sekali (mis. "&" jadi "&amp;"),
    lalu <h1>{{ $title }}</h1> di komponennya escape lagi jadi "&amp;amp;" (dobel), browser cuma
    decode 1 lapis jadi literal "&amp;" kelihatan di layar (dilaporkan user 2026-09-16, nama
    jadwal pakai "&"). :title kirim string MENTAH, biar cuma ke-escape sekali di komponennya. --}}
    <x-page-header :title="$schedule->name . ' - ' . $schedule->company?->name" :back="route('presensi.jadwal')" />

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;border-bottom:1px solid var(--border-soft);margin-bottom:18px;">
        <div>
            <button type="button" wire:click="setTab('jam-kerja')" class="schedule-detail-tab {{ $tab === 'jam-kerja' ? 'is-active' : '' }}">Jam Kerja</button>
            @if ($schedule->schedule_type === 'shift')
                <button type="button" wire:click="setTab('penjadwalan')" class="schedule-detail-tab {{ $tab === 'penjadwalan' ? 'is-active' : '' }}">Detail Penjadwalan</button>
                <button type="button" wire:click="setTab('regu')" class="schedule-detail-tab {{ $tab === 'regu' ? 'is-active' : '' }}">Tim</button>
                @can('viewAny', \App\Models\Shift\ExtraShiftAssignment::class)
                    <button type="button" wire:click="setTab('extra-shift')" class="schedule-detail-tab {{ $tab === 'extra-shift' ? 'is-active' : '' }}">Extra Shift</button>
                @endcan
                <button type="button" wire:click="setTab('template')" class="schedule-detail-tab {{ $tab === 'template' ? 'is-active' : '' }}">Template Shift</button>
            @endif
            <button type="button" wire:click="setTab('kantor')" class="schedule-detail-tab {{ $tab === 'kantor' ? 'is-active' : '' }}">Pengguna</button>
            <button type="button" wire:click="setTab('riwayat')" class="schedule-detail-tab {{ $tab === 'riwayat' ? 'is-active' : '' }}">Riwayat</button>
        </div>
        @if ($this->canEdit)
            <a href="{{ route('presensi.jadwal.edit', $schedule) }}" wire:navigate class="btn-ghost" style="padding:6px 14px;font-size:11.5px;margin-bottom:8px;">
                <i class="fa-solid fa-pen"></i> Edit Jadwal
            </a>
        @endif
    </div>

    {{-- Tab Jam Kerja/Pengguna dipecah per tipe jadwal — lihat resources/views/livewire/presensi/partials/.
         Template Shift/Detail Penjadwalan (shift-only) komponen Livewire nested terpisah, CRUD-nya
         cukup berat buat jadi partial statis. --}}
    @if ($tab === 'jam-kerja' || $tab === 'kantor')
        @if ($schedule->schedule_type === 'shift')
            @include('livewire.presensi.partials.schedule-shift-detail')
        @elseif ($schedule->schedule_type === 'ibs' && $tab === 'jam-kerja')
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:22px;font-size:13px;line-height:1.7;color:var(--text-body);">
                <p style="margin:0 0 8px;font-weight:700;color:var(--text-heading);">Jadwal IBS</p>
                <p style="margin:0;">Tidak ada jam baku per hari. Scan dipasangkan berurutan (masuk, pulang, masuk, pulang), jam kerja adalah jumlah durasi tiap pasangan. Scan masuk tanpa pulang tampil belum pulang. Target 40 jam per minggu.</p>
            </div>
        @else
            @include('livewire.presensi.partials.schedule-day-detail')
        @endif
    @elseif ($tab === 'template')
        <livewire:presensi.schedule-shift-rotation-tab :schedule="$schedule" :key="'rot-'.$schedule->id" />
    @elseif ($tab === 'penjadwalan')
        <livewire:presensi.schedule-shift-roster-tab :schedule="$schedule" :key="'ros-'.$schedule->id" />
    @elseif ($tab === 'regu')
        <livewire:presensi.schedule-shift-team-tab :schedule="$schedule" :key="'regu-'.$schedule->id" />
    @elseif ($tab === 'extra-shift')
        <livewire:presensi.schedule-extra-shift-tab :schedule="$schedule" :key="'extra-'.$schedule->id" />
    @else
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;">
            @forelse ($this->changeLogs as $log)
                <button type="button" wire:click="openLog('{{ $log->id }}')"
                    style="width:100%;text-align:left;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:13px 20px;border:none;border-bottom:1px solid var(--border-soft);background:none;cursor:pointer;font-family:inherit;">
                    <span style="font-size:12.5px;font-weight:600;color:var(--text-heading);">{{ $log->created_at->translatedFormat('d M Y (H:i:s)') }}</span>
                    <span style="font-size:11.5px;color:var(--text-muted);display:flex;align-items:center;gap:10px;">
                        {{ $log->changedBy?->name ?? 'Sistem' }}
                        <i class="fa-solid fa-circle-info" style="color:var(--text-faint);"></i>
                    </span>
                </button>
            @empty
                <div style="padding:32px;text-align:center;">
                    <i class="fa-solid fa-clock-rotate-left" style="font-size:22px;color:var(--text-faint);"></i>
                    <p style="font-size:12.5px;color:var(--text-muted);margin:12px 0 0;">Belum ada riwayat perubahan.</p>
                    <p style="font-size:11px;color:var(--text-faint);margin:4px 0 0;">Jadwal ini belum pernah diubah lewat fitur Edit Jadwal.</p>
                </div>
            @endforelse
        </div>
    @endif

    {{-- Popup "Riwayat Perubahan Jadwal" — kondisi jadwal SEBELUM 1 perubahan diterapkan. --}}
    @if ($this->viewingLog)
        @php
            $snap = $this->viewingLog->snapshot;
            $snapType = $snap['schedule_type'] ?? 'fixed';
            $snapDataColCount = $snapType === 'fixed' ? 9 : 6;
        @endphp
        <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;" wire:click="closeLog">
            <div style="background:var(--surface);border-radius:14px;max-width:920px;width:100%;max-height:85vh;overflow-y:auto;" onclick="event.stopPropagation()">
                <div style="padding:18px 22px;border-bottom:1px solid var(--border-soft);display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <div>
                        <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">Riwayat Perubahan Jadwal</h3>
                        <p style="font-size:11.5px;color:var(--text-muted);margin:4px 0 0;">Di bawah ini adalah data jadwal yang berlaku sebelum perubahan pada {{ $this->viewingLog->created_at->translatedFormat('d M Y, H:i') }} oleh {{ $this->viewingLog->changedBy?->name ?? 'Sistem' }}.</p>
                    </div>
                    <button type="button" wire:click="closeLog" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;flex-shrink:0;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div style="padding:18px 22px;">
                    <p style="font-size:11px;color:var(--text-muted);margin:0 0 12px;">Nama jadwal saat itu: <strong style="color:var(--text-heading);">{{ $snap['name'] ?? '-' }}</strong></p>

                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:11px;table-layout:fixed;">
                            <colgroup>
                                <col style="width:80px;">
                                <col style="width:50px;">
                                @for ($i = 0; $i < $snapDataColCount; $i++)
                                    <col>
                                @endfor
                            </colgroup>
                            <thead>
                                <tr style="border-bottom:1.5px solid var(--border);">
                                    <th style="text-align:left;padding:8px 8px 8px 4px;white-space:nowrap;">Hari</th>
                                    <th style="text-align:center;padding:8px;white-space:nowrap;">Libur</th>
                                    @if ($snapType === 'fixed')
                                        <th colspan="3" style="text-align:center;padding:6px 8px;white-space:nowrap;">Lembur Awal</th>
                                        <th colspan="3" style="text-align:center;padding:6px 8px;white-space:nowrap;background:rgba(4,44,108,0.06);">Jam Kerja</th>
                                        <th colspan="3" style="text-align:center;padding:6px 8px;white-space:nowrap;">Lembur Akhir</th>
                                    @else
                                        <th colspan="3" style="text-align:center;padding:6px 8px;white-space:nowrap;background:rgba(4,44,108,0.06);">Jam Kerja</th>
                                        <th colspan="3" style="text-align:center;padding:6px 8px;white-space:nowrap;">Lembur</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->displayDays as $dow)
                                    @php $row = $snap['days'][$dow] ?? []; $isOff = $row['is_off_day'] ?? false; @endphp
                                    <tr style="border-bottom:1px solid var(--border-soft);{{ $isOff ? 'background:var(--danger-soft-bg);' : '' }}">
                                        <td style="padding:5px 8px 5px 4px;white-space:nowrap;">
                                            <span style="font-weight:600;color:{{ $isOff ? 'var(--danger-soft-text)' : 'var(--text-heading)' }};">{{ $this->dayLabel($dow) }}</span>
                                        </td>
                                        <td style="padding:5px;text-align:center;">
                                            <i class="fa-solid {{ $isOff ? 'fa-check' : 'fa-xmark' }}" style="color:{{ $isOff ? 'var(--danger-soft-text)' : 'var(--text-faint)' }};"></i>
                                        </td>
                                        @if ($isOff)
                                            <td colspan="{{ $snapDataColCount }}" style="padding:8px;">
                                                <span style="font-weight:600;color:var(--danger-soft-text);"><i class="fa-solid fa-mug-hot"></i> Libur</span>
                                            </td>
                                        @elseif ($snapType === 'fixed')
                                            <td style="padding:5px;text-align:center;">{{ $row['overtime_before_min'] ?? 0 }}</td>
                                            <td style="padding:5px;text-align:center;">{{ $row['overtime_before_break'] ?? 0 }}</td>
                                            <td style="padding:5px;text-align:center;">{{ $row['overtime_before_max'] ?? 0 }}</td>
                                            <td style="padding:5px;text-align:center;background:rgba(4,44,108,0.06);">{{ $this->formatTime($row['start_time'] ?? null) }}</td>
                                            <td style="padding:5px;text-align:center;background:rgba(4,44,108,0.06);">{{ $row['break_minutes'] ?? 0 }}</td>
                                            <td style="padding:5px;text-align:center;background:rgba(4,44,108,0.06);">{{ $this->formatTime($row['end_time'] ?? null) }}</td>
                                            <td style="padding:5px;text-align:center;">{{ $row['overtime_after_min'] ?? 0 }}</td>
                                            <td style="padding:5px;text-align:center;">{{ $row['overtime_after_break'] ?? 0 }}</td>
                                            <td style="padding:5px;text-align:center;">{{ $row['overtime_after_max'] ?? 0 }}</td>
                                        @else
                                            <td style="padding:5px;text-align:center;background:rgba(4,44,108,0.06);">{{ $row['break_minutes'] ?? 0 }}</td>
                                            <td style="padding:5px;text-align:center;background:rgba(4,44,108,0.06);">{{ $row['min_work_minutes'] ?? 0 }}</td>
                                            <td style="padding:5px;text-align:center;background:rgba(4,44,108,0.06);">{{ $row['max_work_minutes'] ?? 0 }}</td>
                                            <td style="padding:5px;text-align:center;">{{ $row['overtime_break_minutes'] ?? 0 }}</td>
                                            <td style="padding:5px;text-align:center;">{{ $row['overtime_min_minutes'] ?? 0 }}</td>
                                            <td style="padding:5px;text-align:center;">{{ $row['overtime_max_minutes'] ?? 0 }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top:14px;padding:10px 14px;background:var(--warn-soft-bg);border-radius:10px;font-size:11px;color:var(--warn-soft-text);">
                        <i class="fa-solid fa-circle-info"></i> Hari yang diblok merah artinya libur.
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;padding:14px 22px;border-top:1px solid var(--border-soft);">
                    <button type="button" wire:click="closeLog" class="btn-primary">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
