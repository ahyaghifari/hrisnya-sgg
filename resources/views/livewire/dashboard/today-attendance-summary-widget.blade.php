<div class="card-surface accent accent-green">
    @php $today = $this->summary; @endphp
    <div class="section-head-icon" style="margin-bottom:14px;">
        <div class="section-icon"><i class="fa-solid fa-clipboard-check"></i></div>
        <div>
            <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Attendance Hari Ini</p>
            <p style="font-size:11px;color:var(--text-faint);margin-top:2px;">{{ $today['total'] }} karyawan aktif &middot; klik kategori untuk lihat daftar</p>
        </div>
    </div>
    <div style="position:relative;width:100%;height:220px;">
        <canvas data-chart-config="{{ json_encode($this->chartConfig) }}"></canvas>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4" style="gap:8px;margin-top:14px;">
        <button type="button" wire:click="openModal('hadir')" style="background:#dcfce7;border:none;border-radius:10px;padding:8px 10px;text-align:left;cursor:pointer;font-family:inherit;">
            <p style="font-size:15px;font-weight:800;color:#15803d;margin:0;">{{ $today['hadir'] }}</p>
            <p style="font-size:10.5px;color:#15803d;margin-top:1px;">Hadir</p>
        </button>
        <button type="button" wire:click="openModal('cuti')" style="background:#dbeafe;border:none;border-radius:10px;padding:8px 10px;text-align:left;cursor:pointer;font-family:inherit;">
            <p style="font-size:15px;font-weight:800;color:#1a56c4;margin:0;">{{ $today['cuti'] }}</p>
            <p style="font-size:10.5px;color:#1a56c4;margin-top:1px;">Cuti</p>
        </button>
        <button type="button" wire:click="openModal('izin')" style="background:#fef9c3;border:none;border-radius:10px;padding:8px 10px;text-align:left;cursor:pointer;font-family:inherit;">
            <p style="font-size:15px;font-weight:800;color:#a16207;margin:0;">{{ $today['izin'] }}</p>
            <p style="font-size:10.5px;color:#a16207;margin-top:1px;">Izin</p>
        </button>
        <button type="button" wire:click="openModal('belum')" style="background:var(--surface-alt);border:none;border-radius:10px;padding:8px 10px;text-align:left;cursor:pointer;font-family:inherit;">
            <p style="font-size:15px;font-weight:800;color:var(--text-muted);margin:0;">{{ $today['belum'] }}</p>
            <p style="font-size:10.5px;color:var(--text-muted);margin-top:1px;">Belum Presensi</p>
        </button>
    </div>

    @if ($activeModal === 'hadir')
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Hadir Hari Ini</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $this->hadirDetail->count() }} karyawan sudah presensi masuk.</p>

                @forelse ($this->hadirDetail as $record)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                        <span style="font-size:12.5px;color:var(--text-body);">{{ $record->user?->name ?? '-' }}</span>
                        <span style="display:flex;align-items:center;gap:6px;">
                            <span style="font-size:12px;color:var(--text-muted);">{{ \Illuminate\Support\Carbon::parse($record->check_in_time)->format('H:i') }}</span>
                            @if ($record->late_minutes > 0)
                                <span class="badge badge-pending">Terlambat</span>
                            @endif
                        </span>
                    </div>
                @empty
                    <p style="font-size:12.5px;color:var(--text-muted);">Belum ada yang presensi hari ini.</p>
                @endforelse

                <div style="margin-top:16px;text-align:right;">
                    <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    @if ($activeModal === 'cuti')
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Cuti Hari Ini</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $this->cutiDetail->count() }} karyawan sedang cuti.</p>

                @forelse ($this->cutiDetail as $request)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                        <span style="font-size:12.5px;color:var(--text-body);">{{ $request->user?->name ?? '-' }}</span>
                        <span class="badge badge-info">{{ $request->leaveDetails->first()?->leave_type ?? 'Cuti' }}</span>
                    </div>
                @empty
                    <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada yang cuti hari ini.</p>
                @endforelse

                <div style="margin-top:16px;text-align:right;">
                    <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    @if ($activeModal === 'izin')
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Izin Hari Ini</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $this->izinDetail->count() }} karyawan sedang izin.</p>

                @forelse ($this->izinDetail as $request)
                    @php $detail = $request->permissionDetails->first(); @endphp
                    <div style="padding:8px 0;border-bottom:1px solid var(--border-soft);">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:12.5px;color:var(--text-body);">{{ $request->user?->name ?? '-' }}</span>
                            <span class="badge badge-pending">{{ $detail?->permission_type ?? 'Izin' }}</span>
                        </div>
                        @if ($detail?->start_time && $detail?->end_time)
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $detail->start_time }} &ndash; {{ $detail->end_time }}</p>
                        @endif
                    </div>
                @empty
                    <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada yang izin hari ini.</p>
                @endforelse

                <div style="margin-top:16px;text-align:right;">
                    <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    @if ($activeModal === 'belum')
        <div class="modal-overlay" wire:click="closeModal">
            <div class="modal-box" onclick="event.stopPropagation()">
                <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Belum Presensi</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">{{ $this->belumDetail->count() }} karyawan belum tercatat hadir/cuti/izin hari ini.</p>

                @forelse ($this->belumDetail as $user)
                    <div style="padding:8px 0;border-bottom:1px solid var(--border-soft);">
                        <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $user->name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $user->profile?->jobPosition?->name ?? '-' }}</p>
                    </div>
                @empty
                    <p style="font-size:12.5px;color:var(--text-muted);">Semua karyawan sudah tercatat hari ini.</p>
                @endforelse

                <div style="margin-top:16px;text-align:right;">
                    <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
