@php
    $isSelf = $record->id === auth()->id();
    $backRoute = $isSelf && ! auth()->user()->canAccessKaryawanMenu() ? route('dashboard') : route('my-employees');
@endphp
<div x-data="{ tab: 'profil' }">
<div class="mb-6 flex items-center justify-between">
    <div>
        <p class="text-xs text-slate-500 mb-1">{{ now()->translatedFormat('l, d F Y') }}</p>
        <h1 class="text-[22px] font-extrabold text-slate-900 tracking-tight">{{ $isSelf ? 'Data Diri' : 'Karyawanku - ' . $record->name }}</h1>
        <p class="text-xs text-slate-600 mt-1">{{ $record->profile?->jobPosition?->name ?? 'Karyawan' }}</p>
    </div>
    <a href="{{ $backRoute }}" wire:navigate class="text-sm font-semibold text-gray-600 hover:text-gray-900">
        &larr; Kembali
    </a>
</div>

<!-- Tabs -->
<div class="flex gap-1 border-b border-gray-200 mb-6">
    <button type="button" @click="tab = 'profil'"
        class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors"
        :class="tab === 'profil' ? 'border-blue-700 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'">
        Profil
    </button>
    <button type="button" @click="tab = 'statistik'"
        class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors"
        :class="tab === 'statistik' ? 'border-blue-700 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'">
        Presensi & Absensi
    </button>
    <button type="button" @click="tab = 'riwayat'"
        class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors"
        :class="tab === 'riwayat' ? 'border-blue-700 text-blue-700' : 'border-transparent text-slate-500 hover:text-slate-700'">
        Riwayat Pengajuan
    </button>
</div>

<!-- Tab: Profil -->
<div x-show="tab === 'profil'">
    @php
        $profile = $record->profile;

        $initials = collect(preg_split('/\s+/', trim($record->name)))
            ->map(fn ($w) => mb_substr($w, 0, 1))
            ->take(2)
            ->implode('');

        $roleLabel = $record->roles->pluck('name')
            ->map(fn ($n) => ucwords(str_replace('_', ' ', $n)))
            ->implode(', ');

        $tanggalLahir = $profile?->place_of_birth || $profile?->date_of_birth
            ? trim(($profile?->place_of_birth ?? '') . ', ' . ($profile?->date_of_birth ? \Carbon\Carbon::parse($profile->date_of_birth)->translatedFormat('d F Y') : ''), ', ')
            : null;

        $pribadi = [
            'Nama Panggilan' => $profile?->nickname,
            'Jenis Kelamin' => match ($profile?->gender) { 'laki_laki' => 'Laki-laki', 'perempuan' => 'Perempuan', default => null },
            'Agama' => $profile?->religion?->name,
            'Tempat, Tanggal Lahir' => $tanggalLahir,
            'No. NIK' => $profile?->no_nik,
            'No. KK' => $profile?->no_kk,
            'No. BPJS Kesehatan' => $profile?->no_bpjs_kes,
            'No. BPJS Ketenagakerjaan' => $profile?->no_bpjs_ket,
            'NPWP' => $profile?->npwp,
            'Bank' => $profile?->bank?->name ? "{$profile->bank->name} — {$profile->bank_account_number} a.n. {$profile->bank_account_name}" : null,
        ];

        $kontak = [
            'No. Telepon' => $profile?->phone,
            'No. WhatsApp' => $profile?->whatsapp,
        ];

        $alamatLengkap = collect([$profile?->address, $profile?->village, $profile?->district, $profile?->city, $profile?->province, $profile?->country])
            ->filter()
            ->implode(', ');

        $alamatDetail = [
            'RT/RW' => ($profile?->rt || $profile?->rw) ? "{$profile?->rt}/{$profile?->rw}" : null,
            'Kode Pos' => $profile?->postal_code,
        ];

        $pekerjaan = [
            'Departemen' => $profile?->department?->name,
            'Divisi' => $profile?->division?->name,
            'Sub Divisi' => $profile?->subDivision?->name,
            'Jabatan' => $profile?->jobPosition?->name,
            'Pangkat' => $profile?->pangkat?->name,
            'Golongan' => $profile?->golongan?->name,
            'Kelas' => $profile?->kelas?->name,
            'Tipe Karyawan' => $profile?->employeeType?->name,
            'Tanggal Bergabung' => $profile?->joining_date ? \Carbon\Carbon::parse($profile->joining_date)->translatedFormat('d F Y') : null,
        ];
    @endphp

    @php
        $renderField = function (string $label, ?string $value, bool $showBorder = true) {
            $borderStyle = $showBorder ? 'border-bottom:1px solid #f1f5f9;' : '';
            $valueHtml = $value
                ? '<span style="font-size:13.5px;font-weight:600;color:#334155;text-align:right;">' . e($value) . '</span>'
                : '<span style="font-size:12.5px;font-style:italic;color:#64748b;text-align:right;">Belum diisi</span>';

            return '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:10px 0;' . $borderStyle . '">'
                . '<span style="font-size:12.5px;color:#64748b;flex-shrink:0;">' . e($label) . '</span>'
                . $valueHtml
                . '</div>';
        };

        $sectionHeader = function (string $icon, string $title) {
            return '<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">'
                . '<div style="flex-shrink:0;width:32px;height:32px;border-radius:9px;background:rgba(4,44,108,0.08);display:flex;align-items:center;justify-content:center;">'
                . '<i class="fa-solid ' . $icon . '" style="color:#042c6c;font-size:13px;"></i>'
                . '</div>'
                . '<h4 style="font-size:13.5px;font-weight:700;color:#1e293b;margin:0;">' . e($title) . '</h4>'
                . '</div>';
        };
    @endphp

    {{-- Kartu identitas: jawaban cepat untuk "siapa orang ini" sebelum menyelami detail --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5" style="margin-bottom:20px;">
        <div style="display:flex;flex-wrap:wrap;align-items:flex-start;gap:16px;">
            <div style="flex-shrink:0;width:56px;height:56px;border-radius:50%;background:#042c6c;color:#fff;font-weight:800;font-size:17px;display:flex;align-items:center;justify-content:center;">
                {{ $initials ?: '?' }}
            </div>
            <div style="min-width:0;flex:1;">
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                    <h2 style="font-size:19px;font-weight:800;color:#0f172a;letter-spacing:-0.3px;margin:0;">{{ $record->name }}</h2>
                    <span class="badge {{ $record->is_active ? 'badge-approved' : 'badge-cancelled' }}">
                        {{ $record->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                <p style="font-size:13px;color:#64748b;margin-top:3px;">
                    {{ $profile?->jobPosition?->name ?? 'Belum ada jabatan' }}
                    @if ($profile?->department?->name)
                        <span style="color:#cbd5e1;">·</span> {{ $profile->department->name }}
                    @endif
                </p>
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 20px;margin-top:12px;">
                    <span style="font-size:12.5px;color:#64748b;"><i class="fa-solid fa-id-badge" style="color:#94a3b8;margin-right:6px;"></i>{{ $record->nip ?: 'NIP belum diisi' }}</span>
                    <span style="font-size:12.5px;color:#64748b;"><i class="fa-solid fa-envelope" style="color:#94a3b8;margin-right:6px;"></i>{{ $record->email }}</span>
                    <span style="font-size:12.5px;color:#64748b;"><i class="fa-solid fa-building" style="color:#94a3b8;margin-right:6px;"></i>{{ $record->company?->name }}</span>
                    @if ($roleLabel)
                        <span style="font-size:12.5px;color:#64748b;"><i class="fa-solid fa-user-shield" style="color:#94a3b8;margin-right:6px;"></i>{{ $roleLabel }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Rincian: dikelompokkan per kartu supaya tiap topik bisa dipindai sendiri-sendiri --}}
    <div style="display:flex;flex-wrap:wrap;gap:20px;margin-bottom:20px;">
        <div class="bg-white rounded-xl border border-gray-200 p-5" style="flex:2 1 340px;">
            {!! $sectionHeader('fa-id-card', 'Informasi Pribadi') !!}
            @foreach ($pribadi as $label => $value)
                {!! $renderField($label, $value, !$loop->last) !!}
            @endforeach
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5" style="flex:1 1 220px;align-self:flex-start;">
            {!! $sectionHeader('fa-phone', 'Kontak') !!}
            @foreach ($kontak as $label => $value)
                {!! $renderField($label, $value, !$loop->last) !!}
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5" style="margin-bottom:20px;">
        {!! $sectionHeader('fa-location-dot', 'Alamat') !!}
        <p style="font-size:13.5px;font-weight:500;color:#334155;line-height:1.6;margin:0;">
            {{ $alamatLengkap ?: 'Alamat belum diisi' }}
        </p>
        @if (collect($alamatDetail)->filter()->isNotEmpty())
            <div style="display:flex;flex-wrap:wrap;gap:6px 28px;margin-top:12px;">
                @foreach ($alamatDetail as $label => $value)
                    @if ($value)
                        <span style="font-size:12.5px;color:#64748b;"><span style="color:#64748b;">{{ $label }}:</span> <span style="font-weight:600;color:#334155;">{{ $value }}</span></span>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5">
        {!! $sectionHeader('fa-briefcase', 'Informasi Pekerjaan') !!}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0 32px;">
            @foreach ($pekerjaan as $label => $value)
                {!! $renderField($label, $value) !!}
            @endforeach
        </div>
    </div>
</div>

<!-- Tab: Presensi & Absensi -->
<div x-show="tab === 'statistik'">
    @php
        $todayPresence = $this->todayPresence;
        $presenceBadge = fn (string $status) => match ($status) {
            'present' => 'badge-approved',
            'late', 'permit' => 'badge-pending',
            'absent' => 'badge-rejected',
            default => 'badge-cancelled',
        };
        $formatJam = fn (?string $time) => $time ? \Carbon\Carbon::parse($time)->format('H:i') : '-';
    @endphp

    <!-- Filter periode -->
    <div class="bg-white rounded-xl border border-gray-200 p-5" style="margin-bottom:20px;">
        <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;">
            <div>
                <label class="form-label">Dari Tanggal</label>
                <input type="date" class="form-input" wire:model.live="date_from">
            </div>
            <div>
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" class="form-input" wire:model.live="date_to">
            </div>
            <div>
                <label class="form-label">Status Pengajuan</label>
                <select class="form-input" wire:model.live="status">
                    <option value="all">Semua Status</option>
                    @foreach (\App\Enum\AttendanceReqStatus::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-left:auto;">
                <button type="button" wire:click="export" class="btn-primary">
                    <i class="fa-solid fa-file-excel"></i> Export Excel
                </button>
            </div>
        </div>
    </div>

    <!-- Section: Presensi -->
    <h3 style="font-size:14px;font-weight:800;color:#1e293b;margin-bottom:12px;">Presensi</h3>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:16px;align-items:stretch;">
        <div class="absen-card" style="min-height:0;">
            <div class="a-deco-1"></div><div class="a-deco-2"></div><div class="a-deco-3"></div>
            <div style="position:relative;z-index:1;">
                <span style="font-size:11px;font-weight:700;color:rgba(255,255,255,0.75);text-transform:uppercase;letter-spacing:0.05em;">
                    <i class="fa-regular fa-clock"></i> Presensi Hari Ini
                </span>
                <p style="font-size:11.5px;color:rgba(255,255,255,0.6);margin-top:3px;">{{ now()->translatedFormat('d F Y') }}</p>
            </div>

            @if ($todayPresence)
                <div style="position:relative;z-index:1;display:flex;gap:28px;">
                    <div>
                        <p style="font-size:11px;color:rgba(255,255,255,0.6);margin-bottom:2px;">Jam Masuk</p>
                        <p style="font-size:24px;font-weight:800;color:#fff;letter-spacing:-0.5px;">{{ $formatJam($todayPresence->check_in_time) }}</p>
                    </div>
                    <div>
                        <p style="font-size:11px;color:rgba(255,255,255,0.6);margin-bottom:2px;">Jam Keluar</p>
                        <p style="font-size:24px;font-weight:800;color:#fff;letter-spacing:-0.5px;">{{ $formatJam($todayPresence->check_out_time) }}</p>
                    </div>
                </div>
                <div style="position:relative;z-index:1;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span style="display:inline-block;padding:4px 12px;border-radius:99px;background:rgba(255,255,255,0.16);color:#fff;font-size:11.5px;font-weight:700;">
                        {{ \App\Enum\AttendanceStatus::from($todayPresence->status)->getLabel() }}
                    </span>
                    @if ($todayPresence->late_minutes > 0)
                        <span style="font-size:12px;color:rgba(255,255,255,0.75);">Terlambat {{ $todayPresence->late_minutes }} menit</span>
                    @endif
                </div>
            @else
                <div style="position:relative;z-index:1;">
                    <p style="font-size:14px;font-weight:600;color:#fff;">Belum ada data presensi</p>
                    <p style="font-size:11.5px;color:rgba(255,255,255,0.65);margin-top:4px;">Karyawan belum melakukan presensi hari ini.</p>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Indeks Presensi Periode Ini</p>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                @foreach (\App\Enum\AttendanceStatus::cases() as $case)
                    <span class="badge {{ $presenceBadge($case->value) }}">
                        {{ $case->getLabel() }}: <strong>{{ $this->presenceStats[$case->value] }}</strong>
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Data Presensi -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" style="margin-bottom:28px;">
        <div style="padding:18px 20px 14px;border-bottom:1px solid #f1f5f9;">
            <h4 style="font-size:13.5px;font-weight:700;color:#1e293b;margin:0;">Data Presensi</h4>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr><th>Tanggal</th><th>Jam Masuk</th><th>Jam Keluar</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($this->presenceRecords as $presence)
                        <tr>
                            <td class="text-sm text-slate-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($presence->date)->translatedFormat('d M Y') }}</td>
                            <td class="text-sm font-semibold text-slate-800">{{ $formatJam($presence->check_in_time) }}</td>
                            <td class="text-sm font-semibold text-slate-800">{{ $formatJam($presence->check_out_time) }}</td>
                            <td>
                                <span class="badge {{ $presenceBadge($presence->status) }}">
                                    {{ \App\Enum\AttendanceStatus::from($presence->status)->getLabel() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-sm text-slate-500 py-6">Tidak ada data presensi pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section: Absensi -->
    <h3 style="font-size:14px;font-weight:800;color:#1e293b;margin-bottom:12px;">Absensi</h3>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:16px;">
        <div class="stat-card">
            <div class="stat-card-deco" style="background:#1a56c4;"></div>
            <div style="width:36px;height:36px;background:#dbeafe;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                <i class="fa-solid fa-umbrella-beach" style="color:#1a56c4;font-size:14px;"></i>
            </div>
            <p style="font-size:38px;font-weight:800;color:#1e293b;letter-spacing:-1.5px;line-height:1;">{{ $this->stats['leave'] }}</p>
            <p style="font-size:13px;font-weight:600;color:#64748b;margin-top:6px;">Cuti</p>
        </div>

        <div class="stat-card">
            <div class="stat-card-deco" style="background:#d97706;"></div>
            <div style="width:36px;height:36px;background:#fef3c7;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                <i class="fa-solid fa-clock" style="color:#d97706;font-size:14px;"></i>
            </div>
            <p style="font-size:38px;font-weight:800;color:#1e293b;letter-spacing:-1.5px;line-height:1;">{{ $this->stats['permission'] }}</p>
            <p style="font-size:13px;font-weight:600;color:#64748b;margin-top:6px;">Izin</p>
        </div>

        <div class="stat-card">
            <div class="stat-card-deco" style="background:#047C54;"></div>
            <div style="width:36px;height:36px;background:#dcfce7;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                <i class="fa-solid fa-briefcase-clock" style="color:#15803d;font-size:14px;"></i>
            </div>
            <p style="font-size:38px;font-weight:800;color:#1e293b;letter-spacing:-1.5px;line-height:1;">{{ $this->stats['overtime'] }}</p>
            <p style="font-size:13px;font-weight:600;color:#64748b;margin-top:6px;">Lembur</p>
        </div>
    </div>

    <!-- Saldo Cuti (kondisi sekarang, tidak tunduk filter tanggal/status) -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
        <livewire:leave-balance-card
            type-keyword="tahunan"
            label="Cuti Tahunan"
            icon="fa-sun"
            color="#042c6c"
            icon-bg="rgba(4,44,108,0.1)"
            grid-class=""
            :user-id="$record->id"
            :key="'leave-balance-tahunan-'.$record->id"
        />
        <livewire:leave-balance-card
            type-keyword="sakit"
            label="Cuti Sakit"
            icon="fa-heart-pulse"
            color="#047C54"
            icon-bg="rgba(4,124,84,0.1)"
            grid-class=""
            :user-id="$record->id"
            :key="'leave-balance-sakit-'.$record->id"
        />
    </div>
</div>

<!-- Tab: Riwayat Pengajuan -->
<div x-show="tab === 'riwayat'">
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr><th>Tanggal Pengajuan</th><th>Jenis</th><th>Keterangan</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse ($this->requests as $request)
                        <tr>
                            <td class="text-sm text-slate-500 whitespace-nowrap">{{ $request->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="text-sm font-semibold text-slate-800">
                                {{ ['leave' => 'Cuti', 'permission' => 'Izin', 'overtime' => 'Lembur'][$request->type] ?? $request->type }}
                            </td>
                            <td class="text-sm text-slate-600">{{ $this->keterangan($request) }}</td>
                            <td>
                                <span class="badge {{ ['approved' => 'badge-approved', 'submitted' => 'badge-pending', 'in_review' => 'badge-pending', 'rejected' => 'badge-rejected', 'cancelled' => 'badge-cancelled'][$request->status] ?? 'badge-cancelled' }}">
                                    {{ \App\Enum\AttendanceReqStatus::from($request->status)->getLabel() }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('my-attendance-requests.show', ['record' => $request]) }}" wire:navigate style="font-size:12.5px;font-weight:700;color:#042c6c;">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-sm text-slate-500 py-6">Tidak ada pengajuan pada periode/status ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
