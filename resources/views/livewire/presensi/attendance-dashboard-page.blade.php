<div>
<x-page-header title="Dashboard Presensi & Absensi" subtitle="Denyut kehadiran hari ini — siapa hadir, siapa telat, siapa izin, dan siapa masih ditunggu persetujuannya" />

@if ($this->canBrowseAllCompanies)
    <div class="mb-4" style="max-width:280px;">
        <label class="form-label">Perusahaan</label>
        <select wire:model.live="companyId" class="form-input">
            @foreach ($this->companyOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>
@endif

{{-- SIGNATURE 1 — "kapasitas roster hari ini": satu bar tersegmentasi (bukan 4 kotak pill
terpisah, bukan donut) yang mencakup SELURUH status attendance_records hari ini, termasuk
izin/cuti/sakit — supaya dashboard ini benar2 tentang absensi, bukan cuma jam masuk/pulang. --}}
<div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px 20px;margin-bottom:16px;">
    <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:6px;">
        <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Status Hari Ini</h3>
        <span style="font-size:11px;color:var(--text-muted);">{{ $this->employeeCount }} karyawan &middot; diperbarui otomatis tiap menit</span>
    </div>

    @if (count($this->statusSegments) > 0)
        <div style="display:flex;width:100%;height:14px;border-radius:7px;overflow:hidden;">
            @foreach ($this->statusSegments as $segment)
                <div style="width:{{ $segment['percent'] }}%;background:{{ $segment['color'] }};" title="{{ $segment['label'] }}: {{ $segment['count'] }}"></div>
            @endforeach
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:14px;">
            @foreach ($this->statusSegments as $segment)
                <button
                    type="button"
                    @if ($segment['status'] === 'late') wire:click="openModal('late')"
                    @elseif ($segment['status'] === 'absent') wire:click="openModal('absent')"
                    @endif
                    style="display:flex;align-items:center;gap:7px;border:none;background:none;padding:0;font-family:inherit;cursor:{{ in_array($segment['status'], ['late', 'absent']) ? 'pointer' : 'default' }};"
                >
                    <span style="width:9px;height:9px;border-radius:50%;background:{{ $segment['color'] }};flex-shrink:0;"></span>
                    <span style="font-size:12px;color:var(--text-body);">{{ $segment['label'] }}</span>
                    <span style="font-size:12px;font-weight:700;color:var(--text-heading);font-variant-numeric:tabular-nums;">{{ $segment['count'] }}</span>
                </button>
            @endforeach
        </div>
    @else
        <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Belum ada data presensi hari ini.</p>
    @endif
</div>

<div class="grid grid-cols-1 md:grid-cols-2" style="gap:16px;align-items:start;">
    {{-- SIGNATURE 2 — histogram jam masuk hari ini, dibucket per jam. Ini yang bikin dashboard
    ini beda dari Keuangan (ledger) & Kepegawaian (donut) - satu2nya yang punya cerita "waktu
    dalam sehari", bukan cuma angka ringkasan. --}}
    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Pola Jam Masuk</h3>
        <p style="font-size:11px;color:var(--text-muted);margin:0 0 18px;">Sebaran waktu check-in hari ini, per jam.</p>

        <div style="display:flex;align-items:flex-end;gap:8px;height:130px;">
            @foreach ($this->arrivalHistogram as $bucket)
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;" title="{{ $bucket['label'] }}: {{ $bucket['count'] }} orang">
                    @if ($bucket['count'] > 0)
                        <span style="font-size:10px;color:var(--text-muted);margin-bottom:4px;font-variant-numeric:tabular-nums;">{{ $bucket['count'] }}</span>
                    @endif
                    <div style="width:100%;max-width:26px;height:{{ max($bucket['percent'], $bucket['count'] > 0 ? 4 : 0) }}%;background:{{ $bucket['label'] === 'Lainnya' ? 'var(--border)' : 'linear-gradient(180deg, #0284c7 0%, #047C54 100%)' }};border-radius:6px 6px 2px 2px;min-height:2px;"></div>
                    <span style="font-size:9.5px;color:var(--text-muted);margin-top:6px;font-weight:600;white-space:nowrap;">{{ $bucket['label'] === 'Lainnya' ? 'Lain' : substr($bucket['label'], 0, 2) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <h3 style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">Antrean Persetujuan</h3>
            <span style="font-size:11px;color:var(--text-muted);">{{ $this->pendingApprovalCount }} pengajuan</span>
        </div>

        @forelse ($this->pendingApprovalsList->take(6) as $request)
            <div class="pwa-list-row">
                <div class="pwa-tile-icon neutral"><i class="fa-solid fa-hourglass-half"></i></div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $request->user?->name ?? '-' }}</p>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ ucfirst($request->type) }} &middot; {{ $request->created_at?->translatedFormat('d M Y') }}</p>
                </div>
                <span class="badge badge-pending">{{ \App\Enum\AttendanceReqStatus::from($request->status)->getLabel() }}</span>
            </div>
        @empty
            <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada pengajuan yang menunggu persetujuan.</p>
        @endforelse

        @if ($this->pendingApprovalCount > 6)
            <button type="button" wire:click="openModal('approvals')" style="margin-top:8px;border:none;background:none;cursor:pointer;font-family:inherit;font-size:11px;color:var(--brand);font-weight:600;padding:0;">Lihat semua ({{ $this->pendingApprovalCount }})</button>
        @endif

        @if ($this->nextHoliday)
            <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border-soft);display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-umbrella-beach" style="color:var(--text-muted);font-size:14px;"></i>
                <p style="font-size:11.5px;color:var(--text-muted);margin:0;">Libur berikutnya: <strong style="color:var(--text-body);">{{ $this->nextHoliday->name }}</strong> &middot; {{ $this->nextHoliday->date->translatedFormat('d M Y') }}</p>
            </div>
        @endif
    </div>
</div>

{{-- ===================== MODALS ===================== --}}

@if ($activeModal === 'late')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Terlambat Hari Ini</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Diurutkan dari yang paling lama telatnya. Maks. 20 ditampilkan.</p>

            @forelse ($this->lateTodayList as $record)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                    <span style="font-size:12.5px;color:var(--text-body);">{{ $record->user?->name ?? '-' }}</span>
                    <span style="font-size:12.5px;font-weight:700;color:#b45309;">{{ $record->late_minutes }} menit</span>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada yang terlambat hari ini. 🎉</p>
            @endforelse

            <div style="margin-top:16px;text-align:right;">
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif

@if ($activeModal === 'absent')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Tidak Hadir Hari Ini</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Maks. 20 ditampilkan.</p>

            @forelse ($this->absentTodayList as $record)
                <div style="padding:8px 0;border-bottom:1px solid var(--border-soft);">
                    <span style="font-size:12.5px;color:var(--text-body);">{{ $record->user?->name ?? '-' }}</span>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Semua karyawan hadir hari ini. 🎉</p>
            @endforelse

            <div style="margin-top:16px;text-align:right;">
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif

@if ($activeModal === 'approvals')
    <div class="modal-overlay" wire:click="closeModal">
        <div class="modal-box" onclick="event.stopPropagation()">
            <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0 0 4px;">Antrean Persetujuan</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:0 0 14px;">Maks. 20 ditampilkan, terbaru dulu.</p>

            @forelse ($this->pendingApprovalsList as $request)
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft);">
                    <div>
                        <p style="font-size:12.5px;font-weight:600;color:var(--text-heading);margin:0;">{{ $request->user?->name ?? '-' }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ ucfirst($request->type) }} &middot; {{ $request->created_at?->translatedFormat('d M Y') }}</p>
                    </div>
                    <span class="badge badge-pending">{{ \App\Enum\AttendanceReqStatus::from($request->status)->getLabel() }}</span>
                </div>
            @empty
                <p style="font-size:12.5px;color:var(--text-muted);">Tidak ada pengajuan yang menunggu persetujuan.</p>
            @endforelse

            <div style="margin-top:16px;text-align:right;">
                <button type="button" wire:click="closeModal" class="btn-ghost">Tutup</button>
            </div>
        </div>
    </div>
@endif
</div>
