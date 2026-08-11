@php
    $isSelf = $record->id === auth()->id();
    // Halaman ini punya 2 entry point selain "Data Diri": MyStaff (bawahan) & Kepegawaian
    // (company-wide) — asal-halaman dikirim lewat query string ?from=karyawan supaya tombol
    // Kembali balik ke list yang benar, bukan selalu ke MyStaff.
    $backRoute = match (true) {
        $isSelf && ! auth()->user()->canAccessKaryawanMenu() => route('dashboard'),
        request()->query('from') === 'karyawan' => route('kepegawaian.karyawan'),
        default => route('my-staff'),
    };
@endphp
<div x-data="{ tab: 'profil' }">
<x-page-header
    :title="$isSelf ? 'Data Diri' : $record->name"
    :subtitle="$record->profile?->jobPosition?->name ?? 'Karyawan'"
    :back="$backRoute"
/>

<!-- Navigasi antar-bagian: tombol (bukan tab bergaris bawah) supaya enak disentuh di mobile -->
<div class="section-nav">
    <button type="button" @click="tab = 'profil'" class="section-nav-btn navy" :class="{ 'is-active': tab === 'profil' }">
        <i class="fa-solid fa-id-card"></i>
        <span>Profil</span>
    </button>
    <button type="button" @click="tab = 'statistik'" class="section-nav-btn green" :class="{ 'is-active': tab === 'statistik' }">
        <i class="fa-solid fa-clock"></i>
        <span>{{ $this->showExtraTabs ? 'Presensi & Absensi' : 'Presensi' }}</span>
    </button>
    @if ($this->showExtraTabs)
        <button type="button" @click="tab = 'dokumen'" class="section-nav-btn teal" :class="{ 'is-active': tab === 'dokumen' }">
            <i class="fa-solid fa-folder-open"></i>
            <span>Dokumen</span>
        </button>
        @if ($isSelf)
            <button type="button" @click="tab = 'penggajian'" class="section-nav-btn gold" :class="{ 'is-active': tab === 'penggajian' }">
                <i class="fa-solid fa-money-check-dollar"></i>
                <span>Penggajian</span>
            </button>
        @endif
    @endif
    @if ($this->canViewAuditHistory)
        <button type="button" @click="tab = 'riwayatdata'" class="section-nav-btn navy" :class="{ 'is-active': tab === 'riwayatdata' }">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Riwayat Perubahan</span>
        </button>
    @endif
</div>

<!-- Tab: Profil -->
<div x-show="tab === 'profil'">
    @php
        $profile = $record->profile;

        $initials = collect(preg_split('/\s+/', trim($record->name)))
            ->map(fn ($w) => mb_substr($w, 0, 1))
            ->take(2)
            ->implode('');

        $photoPath = $profile?->profile_picture[0] ?? null;
        $photoUrl = $photoPath && is_string($photoPath) ? \Illuminate\Support\Facades\Storage::url($photoPath) : null;

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
            'Golongan' => $profile?->pangkat?->golongan,
            'Kelas' => $profile?->kelas?->name,
            'Tipe Karyawan' => $profile?->employeeType?->name,
            'Tanggal Bergabung' => $profile?->joining_date ? \Carbon\Carbon::parse($profile->joining_date)->translatedFormat('d F Y') : null,
        ];
    @endphp

    @php
        $renderField = function (string $label, ?string $value, bool $showBorder = true) {
            $borderStyle = $showBorder ? 'border-bottom:1px solid var(--border-soft);' : '';
            $valueHtml = $value
                ? '<span style="font-size:13.5px;font-weight:600;color:var(--text-body);text-align:right;">' . e($value) . '</span>'
                : '<span style="font-size:12.5px;font-style:italic;color:var(--text-muted);text-align:right;">Belum diisi</span>';

            return '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:10px 0;' . $borderStyle . '">'
                . '<span style="font-size:12.5px;color:var(--text-muted);flex-shrink:0;">' . e($label) . '</span>'
                . $valueHtml
                . '</div>';
        };

        $sectionHeader = function (string $icon, string $title) {
            return '<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">'
                . '<div style="flex-shrink:0;width:32px;height:32px;border-radius:9px;background:rgba(4,44,108,0.08);display:flex;align-items:center;justify-content:center;">'
                . '<i class="fa-solid ' . $icon . '" style="color:var(--brand);font-size:13px;"></i>'
                . '</div>'
                . '<h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">' . e($title) . '</h4>'
                . '</div>';
        };
    @endphp

    {{-- Kartu identitas: jawaban cepat untuk "siapa orang ini" sebelum menyelami detail --}}
    <div class="card-surface" style="margin-bottom:20px;">
        <div style="display:flex;flex-wrap:wrap;align-items:flex-start;gap:16px;">
            @if ($record->id === auth()->id())
                {{-- Cuma pemilik akun yang boleh edit foto sendiri di sini - HC/atasan yang buka
                Data Diri orang lain cuma lihat foto (@else di bawah), ditegakkan juga di server
                lewat abort_unless di HandlesProfilePhotoUpload::updatedPhoto(). --}}
                <x-photo-upload-cropper :avatar-url="$photoUrl" :initials="$initials ?: '?'" :size="56" />
                @error('photo') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;width:100%;">{{ $message }}</p> @enderror
            @elseif ($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $record->name }}" style="flex-shrink:0;width:56px;height:56px;border-radius:50%;object-fit:cover;">
            @else
                <div style="flex-shrink:0;width:56px;height:56px;border-radius:50%;background:#042c6c;color:#fff;font-weight:800;font-size:17px;display:flex;align-items:center;justify-content:center;">
                    {{ $initials ?: '?' }}
                </div>
            @endif
            <div style="min-width:0;flex:1;">
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                    <h2 style="font-size:19px;font-weight:800;color:var(--text-heading);letter-spacing:-0.3px;margin:0;">{{ $record->name }}</h2>
                    <span class="badge {{ $record->is_active ? 'badge-approved' : 'badge-cancelled' }}">
                        {{ $record->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                <p style="font-size:13px;color:var(--text-muted);margin-top:3px;">
                    {{ $profile?->jobPosition?->name ?? 'Belum ada jabatan' }}
                    @if ($profile?->department?->name)
                        <span style="color:var(--text-faint);">·</span> {{ $profile->department->name }}
                    @endif
                </p>
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 20px;margin-top:12px;">
                    <span style="font-size:12.5px;color:var(--text-muted);"><i class="fa-solid fa-id-badge" style="color:var(--text-faint);margin-right:6px;"></i>{{ $record->nip ?: 'NIP belum diisi' }}</span>
                    <span style="font-size:12.5px;color:var(--text-muted);"><i class="fa-solid fa-envelope" style="color:var(--text-faint);margin-right:6px;"></i>{{ $record->email }}</span>
                    <span style="font-size:12.5px;color:var(--text-muted);"><i class="fa-solid fa-building" style="color:var(--text-faint);margin-right:6px;"></i>{{ $record->company?->name }}</span>
                    @if ($roleLabel)
                        <span style="font-size:12.5px;color:var(--text-muted);"><i class="fa-solid fa-user-shield" style="color:var(--text-faint);margin-right:6px;"></i>{{ $roleLabel }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($isSelf && $editingProfil)
        {{-- Mode edit: Informasi Pribadi + Kontak + Alamat dalam 1 form. Rekening bank TIDAK
        ikut di sini (lihat komentar saveProfil()) - tetap cuma bisa diubah HC/admin. --}}
        <div class="card-surface" style="margin-bottom:20px;">
            <form wire:submit="saveProfil">
                {!! $sectionHeader('fa-id-card', 'Informasi Pribadi') !!}
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:6px;">
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" wire:model="email">
                        @error('email') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p style="font-size:11.5px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 10px;margin:0 0 20px;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Kalau email diubah, Anda akan otomatis logout setelah Simpan dan harus login lagi pakai email baru.
                </p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:20px;">
                    <div>
                        <label class="form-label">Nama Panggilan</label>
                        <input type="text" class="form-input" wire:model="nickname">
                        @error('nickname') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Jenis Kelamin</label>
                        <select class="form-input" wire:model="gender">
                            <option value="">-- Pilih --</option>
                            <option value="laki_laki">Laki-laki</option>
                            <option value="perempuan">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Agama</label>
                        <select class="form-input" wire:model="religion_id">
                            <option value="">-- Pilih --</option>
                            @foreach ($this->religionOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Tempat Lahir</label>
                        <input type="text" class="form-input" wire:model="place_of_birth">
                    </div>
                    <div>
                        <label class="form-label">Tanggal Lahir</label>
                        <input type="date" class="form-input" wire:model="date_of_birth">
                        @error('date_of_birth') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">No. NIK</label>
                        <input type="text" class="form-input" wire:model="no_nik">
                    </div>
                    <div>
                        <label class="form-label">No. KK</label>
                        <input type="text" class="form-input" wire:model="no_kk">
                    </div>
                    <div>
                        <label class="form-label">No. BPJS Kesehatan</label>
                        <input type="text" class="form-input" wire:model="no_bpjs_kes">
                    </div>
                    <div>
                        <label class="form-label">No. BPJS Ketenagakerjaan</label>
                        <input type="text" class="form-input" wire:model="no_bpjs_ket">
                    </div>
                    <div>
                        <label class="form-label">NPWP</label>
                        <input type="text" class="form-input" wire:model="npwp">
                    </div>
                </div>
                <p style="font-size:11.5px;color:var(--text-muted);margin:-8px 0 20px;">
                    Rekening bank tidak bisa diubah sendiri — hubungi HC kalau perlu diperbarui.
                </p>

                {!! $sectionHeader('fa-phone', 'Kontak') !!}
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:20px;">
                    <div>
                        <label class="form-label">No. Telepon</label>
                        <input type="text" class="form-input" wire:model="phone">
                    </div>
                    <div>
                        <label class="form-label">No. WhatsApp</label>
                        <input type="text" class="form-input" wire:model="whatsapp">
                    </div>
                </div>

                {!! $sectionHeader('fa-location-dot', 'Alamat') !!}
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:14px;">
                    <div style="grid-column:1/-1;">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-input" rows="2" wire:model="address"></textarea>
                    </div>
                    <div>
                        <label class="form-label">Provinsi</label>
                        <input type="text" class="form-input" wire:model="province">
                    </div>
                    <div>
                        <label class="form-label">Kota/Kabupaten</label>
                        <input type="text" class="form-input" wire:model="city">
                    </div>
                    <div>
                        <label class="form-label">Kecamatan</label>
                        <input type="text" class="form-input" wire:model="district">
                    </div>
                    <div>
                        <label class="form-label">Kelurahan/Desa</label>
                        <input type="text" class="form-input" wire:model="village">
                    </div>
                    <div>
                        <label class="form-label">RT/RW</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" class="form-input" wire:model="rt" placeholder="RT">
                            <input type="text" class="form-input" wire:model="rw" placeholder="RW">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Kode Pos</label>
                        <input type="text" class="form-input" wire:model="postal_code">
                    </div>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-check"></i> Simpan
                    </button>
                    <button type="button" wire:click="cancelEditProfil" class="btn-secondary">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    @else
        {{-- Rincian: dikelompokkan per kartu supaya tiap topik bisa dipindai sendiri-sendiri --}}
        <div style="display:flex;flex-wrap:wrap;gap:20px;margin-bottom:20px;">
            <div class="card-surface" style="flex:2 1 340px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    {!! $sectionHeader('fa-id-card', 'Informasi Pribadi') !!}
                    @if ($isSelf)
                        <button type="button" wire:click="startEditProfil" style="background:none;border:none;color:var(--brand);font-size:12.5px;font-weight:600;cursor:pointer;">
                            <i class="fa-solid fa-pen"></i> Ubah
                        </button>
                    @endif
                </div>
                @foreach ($pribadi as $label => $value)
                    {!! $renderField($label, $value, !$loop->last || $isSelf) !!}
                @endforeach
                {{-- No. Rekening SENGAJA cuma tampil untuk pemilik akun sendiri - atasan/HC yang
                buka Data Diri bawahan lewat MyStaff tidak boleh lihat ini (instruksi user
                2026-08-10), beda dari field pribadi lain di atas yang memang boleh dilihat
                atasan/HC. Read-only murni, tidak ada tombol edit - perubahan rekening tetap
                cuma lewat HC/admin (lihat catatan di EmployeeAttendanceStatistics::saveProfil()). --}}
                @if ($isSelf)
                    {!! $renderField('No. Rekening', $profile?->bank_name ? "{$profile->bank_name} — {$profile->bank_account_number} a.n. {$profile->bank_account_name}" : null, false) !!}
                @endif
            </div>

            <div class="card-surface" style="flex:1 1 220px;align-self:flex-start;">
                {!! $sectionHeader('fa-phone', 'Kontak') !!}
                @foreach ($kontak as $label => $value)
                    {!! $renderField($label, $value, !$loop->last) !!}
                @endforeach
            </div>
        </div>

        <div class="card-surface" style="margin-bottom:20px;">
            {!! $sectionHeader('fa-location-dot', 'Alamat') !!}
            <p style="font-size:13.5px;font-weight:500;color:var(--text-body);line-height:1.6;margin:0;">
                {{ $alamatLengkap ?: 'Alamat belum diisi' }}
            </p>
            @if (collect($alamatDetail)->filter()->isNotEmpty())
                <div style="display:flex;flex-wrap:wrap;gap:6px 28px;margin-top:12px;">
                    @foreach ($alamatDetail as $label => $value)
                        @if ($value)
                            <span style="font-size:12.5px;color:var(--text-muted);"><span style="color:var(--text-muted);">{{ $label }}:</span> <span style="font-weight:600;color:var(--text-body);">{{ $value }}</span></span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Lampiran bebas (label ditulis sendiri oleh karyawan, bukan tipe tetap seperti KTP/KK
    saja) — terpisah dari tab "Dokumen" yang isinya surat resmi terbitan admin (lihat
    UserProfileDocument). Kartu ini disembunyikan total dari atasan/HC yang tidak punya
    permission lihat_lampiran_identitas_karyawan, meskipun mereka bisa buka Data Diri karyawan
    itu untuk tab lain. --}}
    @if ($this->canViewProfileDocuments)
    <div class="card-surface" style="margin-bottom:20px;">
        {!! $sectionHeader('fa-paperclip', 'Lampiran') !!}

        @if ($this->profileDocuments->isNotEmpty())
            <div class="pwa-list" style="margin-bottom:14px;">
                @foreach ($this->profileDocuments as $doc)
                    <div class="pwa-list-row">
                        <div class="pwa-tile-icon neutral"><i class="fa-solid fa-file-lines"></i></div>
                        <a href="{{ $doc->file_url }}" target="_blank" rel="noopener" style="flex:1;min-width:0;text-decoration:none;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $doc->label }}</p>
                        </a>
                        @if ($isSelf)
                            <button type="button" wire:click="deleteProfileDocument('{{ $doc->id }}')" wire:confirm="Hapus lampiran ini?" style="background:none;border:none;color:var(--danger-text);cursor:pointer;padding:6px;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($isSelf)
            {{-- Satu kotak tambah lampiran, dipakai berulang - jadi satu-satunya kotak yang
            tampil kalau daftar di atas masih kosong, atau "tambah lagi" kalau sudah ada isinya. --}}
            <div style="border:1px dashed var(--border-soft);border-radius:12px;padding:14px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                <div style="flex:1;min-width:160px;">
                    <label class="form-label">Nama Lampiran</label>
                    <input type="text" class="form-input" wire:model="newDocumentLabel" placeholder="Contoh: KTP, KK, Ijazah">
                    @error('newDocumentLabel') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <div style="flex:1;min-width:160px;">
                    <label class="form-label">File</label>
                    <input type="file" wire:model="newDocumentFile" accept="image/*,.pdf" class="form-input" style="padding:6px;">
                    @error('newDocumentFile') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
                </div>
                <button type="button" wire:click="addProfileDocument" wire:loading.attr="disabled" wire:target="addProfileDocument,newDocumentFile" class="btn-primary">
                    <i class="fa-solid fa-plus"></i> Tambah
                </button>
            </div>
        @endif
    </div>
    @endif

    <div class="card-surface">
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
    <div class="card-surface" style="margin-bottom:20px;">
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
    <h3 style="font-size:14px;font-weight:800;color:var(--text-heading);margin-bottom:12px;">Presensi</h3>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:16px;align-items:stretch;">
        <x-today-attendance-card :status="$this->todayAttendanceStatus" style="min-height:0;" />

        <div class="card-surface">
            <p style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Indeks Presensi Periode Ini</p>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                @foreach (\App\Enum\AttendanceStatus::cases() as $case)
                    <span class="badge {{ $presenceBadge($case->value) }}">
                        {{ $case->getLabel() }}: <strong>{{ $this->presenceStats[$case->value] }}</strong>
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Data Presensi: kalender kiri, tabel kanan (stack di mobile - kalender duluan) -->
    <style>
        .presensi-layout { display:grid; grid-template-columns:1fr; gap:16px; align-items:start; margin-bottom:28px; }
        @media (min-width: 1024px) { .presensi-layout { grid-template-columns:320px 1fr; } }
    </style>
    <div class="presensi-layout">
        {{-- Kiri: kalender bulan berjalan, gabungan presensi harian + pengajuan cuti/izin/lembur --}}
        <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);padding:14px;position:sticky;top:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <button type="button" wire:click="prevMonth" class="btn-ghost" style="padding:5px 10px;font-size:12px;"><i class="fa-solid fa-chevron-left"></i></button>
                <span style="font-size:13px;font-weight:700;color:var(--text-heading);">{{ $this->calendarMonthLabel }}</span>
                <button type="button" wire:click="nextMonth" class="btn-ghost" style="padding:5px 10px;font-size:12px;"><i class="fa-solid fa-chevron-right"></i></button>
            </div>

            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;font-size:10px;font-weight:700;color:var(--text-faint);text-align:center;margin-bottom:4px;">
                <span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span>
            </div>

            @php
                $requestTypeMeta = [
                    'leave' => ['label' => 'Cuti', 'color' => '#1a56c4'],
                    'permission' => ['label' => 'Izin', 'color' => '#d97706'],
                    'overtime' => ['label' => 'Lembur', 'color' => '#047C54'],
                ];
            @endphp
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:3px;">
                @foreach ($this->calendarDays as $cell)
                    @if ($cell === null)
                        <div></div>
                    @else
                        @php
                            $attendance = $cell['attendance'];
                            $requests = $cell['requests'];
                            $primaryRequest = $requests->first();

                            // Prioritas warna sel: pengajuan (cuti/izin/lembur) di atas presensi biasa -
                            // pengajuan menjelaskan KENAPA hari itu tidak presensi normal.
                            if ($primaryRequest) {
                                $meta = $requestTypeMeta[$primaryRequest['type']];
                                $isPending = $primaryRequest['status'] !== 'approved';
                                $cellStyle = "background:color-mix(in srgb, {$meta['color']} " . ($isPending ? '10' : '16') . "%, var(--surface));"
                                    . "color:{$meta['color']};font-weight:700;"
                                    . ($isPending ? "border:1.5px dashed {$meta['color']};" : '');
                            } elseif ($attendance) {
                                $statusColor = match ($attendance->status) {
                                    'present' => '#047C54', 'late' => '#d97706', 'absent' => '#dc2626', default => '#64748b',
                                };
                                $cellStyle = "background:color-mix(in srgb, {$statusColor} 12%, var(--surface));color:{$statusColor};font-weight:700;";
                            } else {
                                $cellStyle = 'color:var(--text-muted);';
                            }

                            $tooltipParts = collect();
                            if ($attendance) {
                                $tooltipParts->push(\App\Enum\AttendanceStatus::from($attendance->status)->getLabel());
                            }
                            foreach ($requests as $marker) {
                                $meta = $requestTypeMeta[$marker['type']];
                                $statusLabel = \App\Enum\AttendanceReqStatus::from($marker['status'])->getLabel();
                                $tooltipParts->push("{$meta['label']} ({$statusLabel})");
                            }
                        @endphp
                        <div
                            @if ($tooltipParts->isNotEmpty()) title="{{ $tooltipParts->implode(', ') }}" @endif
                            style="aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:8px;font-size:11px;{{ $cellStyle }}
                                {{ $cell['date']->isToday() ? 'box-shadow:inset 0 0 0 1.5px #042c6c;' : '' }}">
                            {{ $cell['date']->day }}
                            @if ($primaryRequest || $attendance)
                                <span style="width:4px;height:4px;border-radius:50%;background:currentColor;margin-top:1px;"></span>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>

            <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px 14px;">
                @foreach ($requestTypeMeta as $meta)
                    <span style="font-size:10.5px;color:var(--text-muted);display:inline-flex;align-items:center;gap:4px;">
                        <span style="width:7px;height:7px;border-radius:50%;background:{{ $meta['color'] }};display:inline-block;"></span>{{ $meta['label'] }}
                    </span>
                @endforeach
            </div>
        </div>

        {{-- Kanan: tabel data presensi harian (attendance_records) --}}
        <div class="card-surface-flush">
            <div style="padding:18px 20px 14px;border-bottom:1px solid var(--border-soft);">
                <h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">Data Presensi</h4>
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
    </div>

    @if ($this->showExtraTabs)
        <!-- Section: Absensi -->
        <h3 style="font-size:14px;font-weight:800;color:var(--text-heading);margin-bottom:12px;">Absensi</h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:16px;">
            <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:18px 20px;">
                <div class="stat-card-deco" style="background:#1a56c4;"></div>
                <div style="flex-shrink:0;width:44px;height:44px;background:#dbeafe;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-umbrella-beach" style="color:#1a56c4;font-size:16px;"></i>
                </div>
                <div style="min-width:0;">
                    <p style="font-size:26px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $this->stats['leave'] }}</p>
                    <p style="font-size:12.5px;font-weight:600;color:var(--text-muted);margin-top:4px;">Cuti</p>
                </div>
            </div>

            <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:18px 20px;">
                <div class="stat-card-deco" style="background:#d97706;"></div>
                <div style="flex-shrink:0;width:44px;height:44px;background:#fef3c7;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-clock" style="color:#d97706;font-size:16px;"></i>
                </div>
                <div style="min-width:0;">
                    <p style="font-size:26px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $this->stats['permission'] }}</p>
                    <p style="font-size:12.5px;font-weight:600;color:var(--text-muted);margin-top:4px;">Izin</p>
                </div>
            </div>

            <div class="stat-card" style="display:flex;align-items:center;gap:14px;padding:18px 20px;">
                <div class="stat-card-deco" style="background:#047C54;"></div>
                <div style="flex-shrink:0;width:44px;height:44px;background:#dcfce7;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="fa-solid fa-briefcase-clock" style="color:#15803d;font-size:16px;"></i>
                </div>
                <div style="min-width:0;">
                    <p style="font-size:26px;font-weight:800;color:var(--text-heading);letter-spacing:-0.8px;line-height:1;">{{ $this->stats['overtime'] }}</p>
                    <p style="font-size:12.5px;font-weight:600;color:var(--text-muted);margin-top:4px;">Lembur</p>
                </div>
            </div>
        </div>

        <!-- Saldo Cuti (kondisi sekarang, tidak tunduk filter tanggal/status) -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
            <livewire:leave-balance-card
                type-keyword="tahunan"
                label="Cuti Tahunan"
                icon="fa-sun"
                color="var(--brand)"
                icon-bg="rgba(4,44,108,0.1)"
                grid-class=""
                :user-id="$record->id"
                :key="'leave-balance-tahunan-'.$record->id"
            />
            <livewire:leave-balance-card
                type-keyword="sakit"
                label="Cuti Sakit"
                icon="fa-heart-pulse"
                color="var(--brand-success)"
                icon-bg="rgba(4,124,84,0.1)"
                grid-class=""
                :user-id="$record->id"
                :key="'leave-balance-sakit-'.$record->id"
            />
        </div>

        <!-- Riwayat Pengajuan (dipindah ke bawah Absensi, bukan tab terpisah lagi) -->
        <h3 style="font-size:14px;font-weight:800;color:var(--text-heading);margin:20px 0 12px;">Riwayat Pengajuan</h3>

        <div class="card-surface-flush">
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
                                    <a href="{{ route('my-attendance-requests.show', ['record' => $request]) }}" wire:navigate style="font-size:12.5px;font-weight:700;color:var(--brand);">
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
    @endif
</div>

<!-- Tab: Dokumen -->
<div x-show="tab === 'dokumen'" x-data="{ docType: 'resignation' }">
    @php
        // Sama seperti menu "Jenis Dokumen" di Filament sebelumnya: kiri daftar jenis,
        // kanan isi dokumen dari jenis yang dipilih. OTHER sengaja tidak masuk daftar
        // jenis (juga tidak muncul di getNavigationItems() versi Filament-nya dulu).
        $documentTypes = [
            \App\Enum\UserDocumentType::RESIGNATION,
            \App\Enum\UserDocumentType::RETIREMENT,
            \App\Enum\UserDocumentType::ASSIGNMENT,
            \App\Enum\UserDocumentType::WORK_CERTIFICATE,
            \App\Enum\UserDocumentType::EMPLOYMENT_CONTRACT,
        ];
        $documentsByType = $this->documents->groupBy('document_type');
    @endphp

    <div class="doc-type-layout">
        {{-- Kiri: pilih jenis dokumen (pill row scrollable di mobile, sidebar vertikal di desktop) --}}
        <div class="doc-type-nav">
            @foreach ($documentTypes as $type)
                <button type="button" @click="docType = '{{ $type->value }}'" class="doc-type-btn" :class="{ 'is-active': docType === '{{ $type->value }}' }">
                    <i class="fa-solid fa-file-lines"></i>
                    <span>{{ $type->label() }}</span>
                    <span class="doc-type-count">{{ $documentsByType->get($type->value, collect())->count() }}</span>
                </button>
            @endforeach
        </div>

        {{-- Kanan: dokumen dari jenis yang dipilih --}}
        <div style="flex:1;min-width:0;">
            @foreach ($documentTypes as $type)
                <div x-show="docType === '{{ $type->value }}'">
                    @php $records = $documentsByType->get($type->value, collect()); @endphp
                    @if ($records->isEmpty())
                        <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                            <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada dokumen {{ mb_strtolower($type->label()) }}.</p>
                        </div>
                    @else
                        <div class="pwa-list">
                            @foreach ($records as $document)
                                <a href="{{ $document->file_url }}" target="_blank" rel="noopener" class="pwa-list-row" style="text-decoration:none;">
                                    <div class="pwa-tile-icon neutral"><i class="fa-solid fa-file-lines"></i></div>
                                    <div style="flex:1;min-width:0;">
                                        <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $document->document_number ?: 'Tanpa nomor' }}</p>
                                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                            {{ $document->issued_date?->translatedFormat('d M Y') ?? 'Tanggal belum diisi' }}
                                        </p>
                                    </div>
                                    <i class="fa-solid fa-download" style="color:var(--text-faint);"></i>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>

@if ($isSelf && $this->showExtraTabs)
    <!-- Tab: Penggajian — cuma diri sendiri, riwayat payroll yang sudah dibayar -->
    <div x-show="tab === 'penggajian'">
        @php $payrollHistory = $this->myPayrollHistory; @endphp
        @if ($payrollHistory->isEmpty())
            <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada riwayat gaji yang tercatat sebagai dibayar.</p>
            </div>
        @else
            <div class="pwa-list">
                @foreach ($payrollHistory as $payroll)
                    <a href="{{ route('data-diri.penggajian.show', $payroll->id) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                        <div class="pwa-tile-icon warn"><i class="fa-solid fa-money-check-dollar"></i></div>
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">
                                {{ $payroll->payrollPeriod->start_date->translatedFormat('d M Y') }} - {{ $payroll->payrollPeriod->end_date->translatedFormat('d M Y') }}
                            </p>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                @if ($payroll->payrollPeriod->payment_date)
                                    Dibayar {{ $payroll->payrollPeriod->payment_date->translatedFormat('d M Y') }} &middot;
                                @endif
                                Gaji Bersih Rp{{ number_format($payroll->net_salary, 0, ',', '.') }}
                            </p>
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color:var(--text-faint);font-size:12px;"></i>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif

@if ($this->canViewAuditHistory)
    {{-- Riwayat perubahan data akun/profil/pekerjaan — gabungan User + UserProfiles (lihat
    LogsFieldAuditChanges), mencatat siapa pun yang mengubah (karyawan sendiri via halaman ini,
    atau admin/HC lewat Kepegawaian/Filament) karena hook-nya di level model. --}}
    <div x-show="tab === 'riwayatdata'">
        @php $auditHistory = $this->auditHistory; @endphp
        @if ($auditHistory->isEmpty())
            <div class="pwa-list" style="padding:32px 16px;text-align:center;">
                <p style="font-size:13px;color:var(--text-muted);margin:0;">Belum ada riwayat perubahan data.</p>
            </div>
        @else
            <div class="pwa-list">
                @foreach ($auditHistory as $entry)
                    <div class="pwa-list-row" style="align-items:flex-start;">
                        <div class="pwa-tile-icon navy"><i class="fa-solid fa-pen"></i></div>
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);margin:0;">{{ $entry->label ?? $entry->key }}</p>
                            <p style="font-size:12px;color:var(--text-body);margin-top:3px;">
                                {{ $entry->old_value ?: '(kosong)' }}
                                <i class="fa-solid fa-arrow-right" style="font-size:10px;color:var(--text-faint);margin:0 4px;"></i>
                                {{ $entry->new_value ?: '(kosong)' }}
                            </p>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                                {{ $entry->created_at->translatedFormat('d M Y H:i') }}
                                &middot;
                                oleh {{ $entry->user?->id === $record->id ? 'karyawan sendiri' : ($entry->user?->name ?? 'Sistem') }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
</div>
