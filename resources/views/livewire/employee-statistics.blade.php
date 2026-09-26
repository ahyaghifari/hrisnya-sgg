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
{{-- tab disimpan di sessionStorage supaya tab aktif tidak balik ke default kalau komponen re-mount
lewat wire:navigate (mis. link paginasi/pindah halaman lain). --}}
<div x-data="{ tab: sessionStorage.getItem('easTab') || 'career' }" x-init="$watch('tab', v => sessionStorage.setItem('easTab', v))">
<x-page-header
    :title="$isSelf ? 'Profil Saya' : $record->name"
    :back="$backRoute"
/>

@php
    $profile = $record->profile;

    $initials = collect(preg_split('/\s+/', trim($record->name)))
        ->map(fn ($w) => mb_substr($w, 0, 1))
        ->take(2)
        ->implode('');

    $photoPath = $profile?->profile_picture[0] ?? null;
    $photoUrl = $photoPath && is_string($photoPath) ? \Illuminate\Support\Facades\Storage::url($photoPath) : null;
@endphp

@php
    // Merit Point dipindah ke samping kartu identitas (instruksi user) - dihitung di sini
    // (sebelum identitas) supaya tersedia buat wrapper flex di bawah. Gerbang sama seperti
    // sebelumnya: kill-switch global showSpMeritWidget + tab Pembinaan + setting per company.
    $showMeritBeside = $this->showSpMeritWidget && $this->canViewMeritWidget && $this->meritPointEnabled;
    if ($showMeritBeside) {
        $hrBalance = $this->hrBalance;
        // Belum ada baris di user_hr_balances = belum pernah kena potongan/tambahan sama
        // sekali → dianggap skor penuh (100), bukan 0 (instruksi user).
        $meritPoint = $hrBalance?->merit_point ?? 100;
        $meritPct = max(0, min(100, $meritPoint));
        // Satu keluarga visual dengan .welcome-card/.absen-card (dark gradient, teks putih,
        // dekorasi bulat) tapi warnanya ikut tier skor - lihat DESIGN.md §domain card.
        // Tier warna (semuanya jadi CSS variable inline di .merit-card, lihat app.css):
        //  from/mid/to = gradasi kartu, ink/btnBg/btnBd = teks & tombol di atasnya,
        //  ring2/ring/track = busur progres ring (track = sisa yang belum terisi), spark = glow ring,
        //  coinA/B/C/Ink = koin terang di tengah ring (angka gelap bernuansa tier di atasnya).
        // Emas (>=90) = palet sama tombol "Profil Saya" (.data-diri-btn.gold): kartunya TERANG, jadi
        // teks/busur ring dibikin cokelat tua (bukan putih) supaya tetap kebaca.
        $meritTier = match (true) {
            $meritPct >= 90 => [
                'from' => '#fde68a', 'mid' => '#f5c518', 'to' => '#d4a017',
                'ink' => '#4a3204', 'btnBg' => 'rgba(74,50,4,.10)', 'btnBd' => 'rgba(74,50,4,.24)',
                'ring2' => '#b7791f', 'ring' => '#7a4f01', 'track' => 'rgba(255,255,255,.55)', 'spark' => '#ffffff',
                'coinA' => '#fffdf5', 'coinB' => '#fde68a', 'coinC' => '#f5c518', 'coinInk' => '#6b4a05',
                'glow' => 'rgba(180,120,4,.5)',
            ],
            $meritPct >= 70 => [
                'from' => '#047C54', 'mid' => '#035f41', 'to' => '#024732',
                'ink' => '#ffffff', 'btnBg' => 'rgba(255,255,255,.14)', 'btnBd' => 'rgba(255,255,255,.24)',
                'ring2' => '#a7f3d0', 'ring' => '#34d399', 'track' => 'rgba(255,255,255,.25)', 'spark' => '#34d399',
                'coinA' => '#f0fdf7', 'coinB' => '#a7f3d0', 'coinC' => '#34d399', 'coinInk' => '#065f46',
                'glow' => 'rgba(4,71,50,.35)',
            ],
            $meritPct >= 40 => [
                'from' => '#b45309', 'mid' => '#964410', 'to' => '#78350f',
                'ink' => '#ffffff', 'btnBg' => 'rgba(255,255,255,.14)', 'btnBd' => 'rgba(255,255,255,.24)',
                'ring2' => '#fde68a', 'ring' => '#fbbf24', 'track' => 'rgba(255,255,255,.25)', 'spark' => '#fbbf24',
                'coinA' => '#fffbeb', 'coinB' => '#fde68a', 'coinC' => '#fbbf24', 'coinInk' => '#78350f',
                'glow' => 'rgba(120,53,15,.35)',
            ],
            default => [
                'from' => '#b91c1c', 'mid' => '#9c1c1c', 'to' => '#7f1d1d',
                'ink' => '#ffffff', 'btnBg' => 'rgba(255,255,255,.14)', 'btnBd' => 'rgba(255,255,255,.24)',
                'ring2' => '#fecaca', 'ring' => '#f87171', 'track' => 'rgba(255,255,255,.25)', 'spark' => '#f87171',
                'coinA' => '#fff5f5', 'coinB' => '#fecaca', 'coinC' => '#fca5a5', 'coinInk' => '#7f1d1d',
                'glow' => 'rgba(127,29,29,.35)',
            ],
        };
        // Emoji sisi belakang koin (flip bergantian dengan angka) - makin rendah skor makin sedih.
        $meritEmoji = match (true) {
            $meritPct >= 100 => '🤩',
            $meritPct >= 90 => '🔥',
            $meritPct >= 80 => '😎',
            $meritPct >= 70 => '👍',
            $meritPct >= 55 => '🙂',
            $meritPct >= 40 => '😐',
            $meritPct >= 25 => '😟',
            $meritPct >= 10 => '😢',
            default => '😭',
        };
    }
@endphp

{{-- Kartu identitas: tampil di atas semua tab, jawaban cepat "siapa orang ini". Class
identity-accent = border gradasi warna section-nav (lihat app.css); warna teks tetap token tema.
Merit Point digabung jadi 1 kartu di sini (bukan kartu terpisah di sampingnya, instruksi user) -
gauge-nya jadi item ketiga di ia-row, sejajar avatar & body, nempel ujung kanan di desktop.
Mobile tetap wrap ke bawah (ruang sempit, ia-row berubah jadi column lewat rule
.identity-accent .ia-row di app.css) - flex-wrap TIDAK disentuh di sini, default browser
untuk flex row sudah nowrap, jadi merit-slot otomatis nempel 1 baris di desktop tanpa perlu
override tambahan. --}}
<style>
    .ia-merit-slot { flex: 0 0 auto; margin-left: auto; }
    @media (max-width: 768px) {
        .ia-merit-slot { margin-left: 0; width: 100%; }
    }
</style>
<div class="card-surface identity-accent" style="margin-bottom:20px;">
    <div class="ia-row" style="display:flex;align-items:center;gap:16px;">
        @if ($record->id === auth()->id())
            {{-- Cuma pemilik akun yang boleh edit foto sendiri di sini - HC/atasan yang buka
            Data Diri orang lain cuma lihat foto (@else di bawah), ditegakkan juga di server
            lewat abort_unless di HandlesProfilePhotoUpload::updatedPhoto(). --}}
            <x-photo-upload-cropper :avatar-url="$photoUrl" :initials="$initials ?: '?'" :size="88" />
            @error('photo') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;width:100%;">{{ $message }}</p> @enderror
        @elseif ($photoUrl)
            <img src="{{ $photoUrl }}" alt="{{ $record->name }}" style="flex-shrink:0;width:88px;height:88px;border-radius:50%;object-fit:cover;">
        @else
            <div style="flex-shrink:0;width:88px;height:88px;border-radius:50%;background:#042c6c;color:#fff;font-weight:800;font-size:26px;display:flex;align-items:center;justify-content:center;">
                {{ $initials ?: '?' }}
            </div>
        @endif
        <div class="ia-body" style="min-width:0;flex:1;">
            <div class="ia-head" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                <h2 class="ia-name" style="font-size:19px;font-weight:800;color:var(--text-heading);letter-spacing:-0.3px;margin:0;">{{ $record->name }}</h2>
                <span class="badge {{ $record->is_active ? 'badge-approved' : 'badge-cancelled' }}">
                    {{ $record->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </div>
            <p class="ia-role" style="font-size:13px;color:var(--text-muted);margin-top:3px;">
                {{ $profile?->jobPosition?->name ?? 'Belum ada jabatan' }}
                @if ($profile?->department?->name)
                    <span style="color:var(--text-faint);">·</span> {{ $profile->department->name }}
                @endif
                @if ($record->company?->name)
                    <span style="color:var(--text-faint);">·</span> {{ $record->company->name }}
                @endif
            </p>
            <div class="ia-meta" style="display:flex;flex-wrap:wrap;align-items:center;gap:6px 20px;margin-top:12px;">
                <span style="font-size:12.5px;color:var(--text-muted);"><i class="fa-solid fa-id-badge" style="color:var(--text-faint);margin-right:6px;"></i>{{ $record->nip ?: 'NIP belum diisi' }}</span>
                <span style="font-size:12.5px;color:var(--text-muted);"><i class="fa-solid fa-envelope" style="color:var(--text-faint);margin-right:6px;"></i>{{ $record->email }}</span>
            </div>
        </div>

        @if ($showMeritBeside)
            <div class="ia-merit-slot">
                <div class="merit-card" style="--merit-from:{{ $meritTier['from'] }};--merit-mid:{{ $meritTier['mid'] }};--merit-to:{{ $meritTier['to'] }};--merit-glow:{{ $meritTier['glow'] }};--merit-ink:{{ $meritTier['ink'] }};--merit-btn-bg:{{ $meritTier['btnBg'] }};--merit-btn-bd:{{ $meritTier['btnBd'] }};--merit-spark:{{ $meritTier['spark'] }};--merit-coin-a:{{ $meritTier['coinA'] }};--merit-coin-b:{{ $meritTier['coinB'] }};--merit-coin-c:{{ $meritTier['coinC'] }};--merit-coin-ink:{{ $meritTier['coinInk'] }};">
                    <div class="merit-deco-1"></div>
                    <div class="merit-deco-2"></div>
                    <div class="merit-gauge-ring" style="width:72px;height:72px;--merit-gauge-bg:conic-gradient({{ $meritTier['ring2'] }} 0deg, {{ $meritTier['ring'] }} {{ $meritPct * 3.6 }}deg, {{ $meritTier['track'] }} {{ $meritPct * 3.6 }}deg 360deg);">
                        <div class="merit-medal">
                            <div class="merit-flip">
                                <div class="merit-face"><span class="num">{{ $meritPoint }}</span></div>
                                <div class="merit-face is-back" aria-hidden="true"><span class="emo">{{ $meritEmoji }}</span></div>
                            </div>
                        </div>
                    </div>
                    {{-- text-align:left INLINE (bukan cuma di app.css): di layar kecil .ia-row memusatkan seluruh
                    teks kartu identitas, dan inline tidak butuh build CSS ulang. --}}
                    <div style="text-align:left;">
                        <p class="merit-label" style="text-align:left;">{{ $isSelf ? 'Poin Anda' : 'Merit Point' }}</p>
                        <div style="display:flex;gap:6px;align-items:center;justify-content:flex-start;margin-top:4px;flex-wrap:wrap;">
                            @if ($isSelf)
                                <button type="button" wire:click="openMeritHistoryModal" class="merit-history-btn">
                                    <i class="fa-solid fa-clock-rotate-left"></i> Riwayat
                                </button>
                            @endif
                            @if ($this->canCreateMeritTransaction)
                                <button type="button" wire:click="openMeritTransactionModal" class="merit-history-btn">
                                    <i class="fa-solid fa-plus-minus"></i> Transaksi
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Navigasi antar-bagian: tombol (bukan tab bergaris bawah) supaya enak disentuh di mobile -->
{{-- Mobile: kolom tetap sesuai jumlah tab maksimal yang mungkin tampil - 5 kalau ada tab Penggajian (cuma
pemilik akun, itu pun kalau tipe karyawannya sudah diisi), selain itu 4 (Karir/Pekerjaan, Data Diri, Kehadiran,
Pembinaan). Inline supaya tidak perlu build CSS; desktop (>=768px) tetap pill horizontal dari app.css. --}}
@php $navCols = ($isSelf && $this->showExtraTabs) ? 5 : 4; @endphp
<style>
    @media (max-width: 767px) {
        .section-nav.section-nav-tiles.nav-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); justify-content: stretch; gap: 10px 6px; }
        .section-nav.section-nav-tiles.nav-cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); justify-content: stretch; gap: 10px 4px; }
        .section-nav.section-nav-tiles[class*="nav-cols-"] .section-nav-btn { min-width: 0; width: 100%; padding-left: 2px; padding-right: 2px; }
    }
    /* Dark mode: tab AKTIF tampil sama dengan light (emas penuh + ikon & label putih). Tanpa ini rule
       html.dark .section-nav-tiles .section-nav-btn.gold-shiny (tint transparan) menimpa background aktif. */
    html.dark .section-nav-tiles .section-nav-btn.gold-shiny.is-active { background: linear-gradient(135deg, #f5c518 0%, #d4a017 100%); box-shadow: 0 6px 14px rgba(212,160,23,0.42); }
    html.dark .section-nav-tiles .section-nav-btn.gold-shiny.is-active i { color: #fff !important; }
    html.dark .section-nav-tiles .section-nav-btn.gold-shiny.is-active span { color: #fff; font-weight: 700; }
</style>
<div class="section-nav section-nav-tiles nav-cols-{{ $navCols }}">
    <button type="button" @click="tab = 'career'" class="section-nav-btn gold-shiny" :class="{ 'is-active': tab === 'career' }">
        <img src="{{ asset('ico/karir.png') }}" alt="" class="section-nav-img">
        <span>{{ $isSelf ? 'Karir' : 'Pekerjaan' }}</span>
    </button>
    <button type="button" @click="tab = 'profil'" class="section-nav-btn gold-shiny" :class="{ 'is-active': tab === 'profil' }">
        <img src="{{ asset('ico/data-diri.png') }}" alt="" class="section-nav-img">
        <span>Data Diri</span>
    </button>
    <button type="button" @click="tab = 'statistik'" class="section-nav-btn gold-shiny" :class="{ 'is-active': tab === 'statistik' }">
        <img src="{{ asset('ico/absensi.png') }}" alt="" class="section-nav-img">
        <span>Kehadiran</span>
    </button>
    @if ($this->showExtraTabs)
        {{-- Tab Dokumen dilepas dulu (2026-09-26, instruksi user) - isinya tumpang tindih modul
        lain: Pengunduran Diri/Pensiun masuk PHK, Surat Tugas rencana ke modul Tugas, Surat
        Keterangan Kerja cukup internal. Konten & getDocumentsProperty() tidak dihapus, tinggal
        dikembalikan kalau perlu lagi - lihat git history baris ini. --}}
        @if ($isSelf)
            <button type="button" @click="tab = 'penggajian'" class="section-nav-btn gold-shiny" :class="{ 'is-active': tab === 'penggajian' }">
                <img src="{{ asset('ico/penggajian.png') }}" alt="" class="section-nav-img">
                <span>Penggajian</span>
            </button>
        @endif
    @endif
    @if ($this->canViewPembinaanTab)
        <button type="button" @click="tab = 'pembinaan'" class="section-nav-btn gold-shiny" :class="{ 'is-active': tab === 'pembinaan' }">
            <img src="{{ asset('ico/pembinaan.png') }}" alt="" class="section-nav-img">
            <span>Pembinaan</span>
        </button>
    @endif
    {{-- Tab "Riwayat" (perubahan data) dilepas dari baris tab (2026-09-26, instruksi user - tab jangan
    kebanyakan): dibuka lewat tombol "Riwayat Perubahan" di kartu Informasi Pribadi (tab Data Diri)
    sebagai panel geser, sama polanya dengan Riwayat Merit Point. --}}
</div>

<!-- Tab: Profil -->
<div x-show="tab === 'profil'">
    @php
        $profile = $record->profile;

        $tanggalLahir = $profile?->place_of_birth || $profile?->date_of_birth
            ? trim(($profile?->place_of_birth ?? '') . ', ' . ($profile?->date_of_birth ? \Carbon\Carbon::parse($profile->date_of_birth)->translatedFormat('d F Y') : ''), ', ')
            : null;

        $pribadi = [
            'Nama Panggilan' => $profile?->nickname,
            'Email Pribadi' => $profile?->email_pribadi,
            'Jenis Kelamin' => match ($profile?->gender) { 'laki_laki' => 'Laki-laki', 'perempuan' => 'Perempuan', default => null },
            'Status Perkawinan' => $profile?->marital_status,
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
                        <label class="form-label">Email Pribadi</label>
                        <input type="email" class="form-input" wire:model="email_pribadi" placeholder="nama@email.com">
                        @error('email_pribadi') <p style="color:var(--danger-text);font-size:11px;margin-top:4px;">{{ $message }}</p> @enderror
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
                        <label class="form-label">Status Perkawinan</label>
                        <input type="text" class="form-input" wire:model="marital_status" maxlength="50">
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
        {{-- Semua kartu full-width 1 kolom. Yang dibagi jadi multi-kolom cuma ISI kartu
        Informasi Pribadi (grid auto-fit di bawah), bukan kartunya. --}}
        <div class="card-surface" style="margin-bottom:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                {!! $sectionHeader('fa-id-card', 'Informasi Pribadi') !!}
                <div style="display:flex;align-items:center;gap:16px;">
                    @if ($this->canViewAuditHistory)
                        <button type="button" wire:click="openAuditHistoryPanel" title="Riwayat Perubahan" aria-label="Riwayat Perubahan" style="background:none;border:none;color:var(--brand);font-size:15px;padding:4px 6px;cursor:pointer;line-height:1;">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </button>
                    @endif
                    @if ($isSelf)
                        <button type="button" wire:click="startEditProfil" style="background:none;border:none;color:var(--brand);font-size:12.5px;font-weight:600;cursor:pointer;">
                            <i class="fa-solid fa-pen"></i> Ubah
                        </button>
                    @endif
                </div>
            </div>
            <div class="field-cols">
                @foreach ($pribadi as $label => $value)
                    {!! $renderField($label, $value) !!}
                @endforeach
                {{-- Rekening bank SENGAJA cuma tampil untuk pemilik akun sendiri - atasan/HC yang
                buka Data Diri bawahan lewat MyStaff tidak boleh lihat ini (instruksi user
                2026-08-10), beda dari field pribadi lain di atas yang memang boleh dilihat
                atasan/HC. Read-only murni, tidak ada tombol edit - perubahan rekening tetap
                cuma lewat HC/admin (lihat catatan di EmployeeStatistics::saveProfil()). --}}
                @if ($isSelf)
                    {!! $renderField('Bank', $profile?->bank_name) !!}
                    {!! $renderField('No. Rekening', $profile?->bank_account_number) !!}
                    {!! $renderField('Atas Nama Rekening', $profile?->bank_account_name) !!}
                @endif
            </div>
        </div>

        <div class="card-surface" style="margin-bottom:20px;">
            {!! $sectionHeader('fa-phone', 'Kontak') !!}
            @foreach ($kontak as $label => $value)
                {!! $renderField($label, $value, !$loop->last) !!}
            @endforeach
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

        {{-- Lampiran bebas (label ditulis sendiri oleh karyawan, bukan tipe tetap seperti
        KTP/KK saja) - terpisah dari tab "Dokumen" yang isinya surat resmi terbitan admin
        (lihat UserProfileDocument). Kartu ini disembunyikan total dari atasan/HC yang tidak
        punya permission lihat_lampiran_identitas_karyawan, meskipun mereka bisa buka Data
        Diri karyawan itu untuk tab lain. ponytail: cuma tampil di mode lihat (bukan form edit). --}}
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
    @endif

    @if ($this->canDeleteEmployee)
        {{-- Sementara: hapus manual (soft delete) sebelum PHK terjadwal — gerbang pakai
        permission Shield Delete:User (UserPolicy::delete), lihat getCanDeleteEmployeeProperty(). --}}
        <button type="button" wire:click="deleteEmployee" wire:confirm="Yakin hapus akun karyawan ini? Akun tidak akan bisa login lagi." class="btn-ghost" style="color:var(--danger-text);border-color:var(--danger-text);font-size:12px;padding:6px 12px;">
            <i class="fa-solid fa-trash"></i> Hapus Akun Karyawan
        </button>
    @endif

</div>

{{-- Tab Karir (label "Karir" kalau lihat profil sendiri, "Pekerjaan" kalau lihat orang lain):
posisi & jenjang karyawan, dan nanti penilaian kinerja. Var profil dan
sectionHeader dibuat di php-block tab Data Diri di atas -- blok ini HARUS setelah tab itu di
source (tab server-render sekali jalan, x-show cuma toggle CSS). --}}
<div x-show="tab === 'career'">
    @php
        $joinDate = $profile?->joining_date ? \Carbon\Carbon::parse($profile->joining_date) : null;
        // Cukup bulan + hari (bukan tahun) - instruksi user.
        $masaKerja = null;
        if ($joinDate) {
            $d = $joinDate->diff(now());
            $bulan = $d->y * 12 + $d->m;
            $masaKerja = "{$bulan} bulan {$d->d} hari";
        }

        // Penempatan di struktur = sebuah jalur (Departemen > Divisi > Sub Divisi), yang buntu
        // di Jabatan. Yang kosong dilewati.
        $orgPath = collect([
            $profile?->department?->name,
            $profile?->division?->name,
            $profile?->subDivision?->name,
        ])->filter()->values();

        // Tile jenjang: cuma tampil yang ada isinya (Masa Kerja selalu ada slot-nya).
        // Pangkat & Golongan disatukan jadi 1 tile "Pangkat/Gol" (mis. "Penata / III").
        $pangkatGol = collect([$profile?->pangkat?->name, $profile?->pangkat?->golongan])
            ->filter()
            ->implode(' / ');
        $jenjang = collect([
            ['label' => 'Pangkat/Gol', 'value' => $pangkatGol],
            ['label' => 'Kelas', 'value' => $profile?->kelas?->name],
        ])->filter(fn ($t) => filled($t['value']));
    @endphp

    <div class="card-surface" style="margin-bottom:20px;">
        {{-- Kepala panel: Jabatan sebagai judul, jalur struktur sebagai jejak di bawahnya.
        Tipe/status karyawan tidak diulang di sini — sudah ada di kartu Status Kepegawaian.
        Mobile: posisi lalu 3 mini-card grid di bawahnya. Desktop: 1 row, posisi kiri
        3 mini-card kanan. --}}
        <style>
            .career-head { display:flex; flex-direction:column; }
            .career-head-left { min-width:0; }
            .career-divider { height:1px; background:var(--border-soft); margin:16px 0; }
            .career-mini-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
            .career-mini-cards .mini-card { background:var(--surface-alt); border:1px solid var(--border-soft); border-radius:12px; padding:10px 8px; min-width:0; }
            .career-mini-cards .mini-card .mini-label { font-size:9px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; color:var(--text-muted); margin:0 0 4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .career-mini-cards .mini-card .mini-value { font-size:13px; font-weight:800; color:var(--text-heading); margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .career-mini-cards .mini-card .mini-sub { font-size:9.5px; color:var(--text-faint); margin-top:2px; }
            @media (min-width:768px) {
                .career-head { flex-direction:row; align-items:center; gap:24px; }
                .career-head-left { flex:1 1 auto; }
                .career-divider { display:none; }
                .career-mini-cards { display:flex; justify-content:flex-end; gap:12px; flex:0 0 auto; }
                .career-mini-cards .mini-card { flex:0 0 170px; padding:14px 16px; }
                .career-mini-cards .mini-card .mini-label { font-size:10.5px; }
                .career-mini-cards .mini-card .mini-value { font-size:16px; }
                .career-mini-cards .mini-card .mini-sub { font-size:11.5px; }
            }
        </style>
        <div class="career-head">
        <div class="career-head-left">
            <p style="font-size:10.5px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-faint);margin:0 0 4px;">Posisi</p>
            <h3 style="font-size:20px;font-weight:800;color:var(--brand);letter-spacing:-0.3px;line-height:1.2;margin:0;">
                {{ $profile?->jobPosition?->name ?? 'Belum ada jabatan' }}
            </h3>
            @if ($orgPath->isNotEmpty())
                <p style="font-size:12.5px;color:var(--text-muted);margin-top:6px;line-height:1.6;">
                    @foreach ($orgPath as $seg)
                        @if (! $loop->first)<span style="color:var(--text-faint);margin:0 6px;">›</span>@endif{{ $seg }}
                    @endforeach
                </p>
            @endif
        </div>

        <div class="career-divider"></div>

        {{-- Masa kerja + jenjang: angka yang dipakai penilaian kinerja nanti. --}}
        <div class="career-mini-cards">
            <div class="mini-card">
                <p class="mini-label">Masa Kerja</p>
                <p class="mini-value">{{ $masaKerja ?? '—' }}</p>
                @if ($joinDate)
                    <p class="mini-sub">sejak {{ $joinDate->translatedFormat('d F Y') }}</p>
                @endif
            </div>
            @foreach ($jenjang as $tile)
                <div class="mini-card">
                    <p class="mini-label">{{ $tile['label'] }}</p>
                    <p class="mini-value">{{ $tile['value'] }}</p>
                </div>
            @endforeach
        </div>
        </div>
    </div>

    {{-- Penilaian kinerja - belum ada modulnya, kartu ini penanda tempat + empty state jujur. --}}
    <div class="card-surface" style="margin-bottom:20px;">
        {!! $sectionHeader('fa-chart-line', 'Penilaian Kinerja') !!}
        <p style="font-size:13px;color:var(--text-muted);margin:0;line-height:1.6;">
            Belum ada penilaian kinerja. Hasil penilaian periodik dari atasan akan tampil di sini.
        </p>
    </div>

    {{-- Status Kepegawaian: fase berjalan (ringkas) + timeline HORIZONTAL perjalanan
    Bergabung → Orientasi → Kontrak/perpanjangan → Tetap dalam satu baris. Data dari ledger
    user_orientations & user_contracts, lihat
    EmployeeStatistics::getEmploymentJourneyProperty(). --}}
    @php
        $journey = $this->employmentJourney;
        $sm = $journey['summary'];
        $ejAccent = match ($journey['category']) {
            'orientasi' => '#b45309',
            'kontrak' => 'var(--brand)',
            'tetap' => '#047C54',
            default => 'var(--text-muted)',
        };
        $ejRibbon = ['orientasi' => 'Orientasi', 'kontrak' => 'Kontrak', 'tetap' => 'Tetap'];
        $ejCurRank = $this->phaseRank($journey['category']);
    @endphp
    <style>
        .emp-journey .ej-ribbon { display:flex; gap:4px; flex-shrink:0; }
        .emp-journey .ej-seg {
            font-size:10px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase;
            padding:5px 10px; border-radius:99px; background:var(--surface-alt);
            color:var(--text-faint); border:1px solid var(--border-soft); white-space:nowrap;
        }
        .emp-journey .ej-seg.is-on { color:#fff; border-color:transparent; }
        .emp-journey .ej-seg.on-orientasi { background:#b45309; }
        .emp-journey .ej-seg.on-kontrak { background:var(--brand); }
        .emp-journey .ej-seg.on-tetap { background:#047C54; }
        .emp-journey .ej-bar { height:6px; border-radius:99px; background:var(--surface-alt); overflow:hidden; margin-top:14px; }
        .emp-journey .ej-bar > span { display:block; height:100%; border-radius:99px; background:var(--ej-accent); transition:width .4s ease; }

        /* Timeline horizontal — semua tahap (Bergabung → Orientasi → Kontrak/perpanjangan →
           Tetap) satu baris, scroll ke samping kalau tahapnya banyak. overflow-x:auto ikut
           meng-clip sumbu-Y, jadi dot + ring HARUS muat di dalam padding track (top/bottom). */
        .emp-journey .ej-track {
            display:flex; overflow-x:auto; margin-top:18px; padding:6px 0 2px;
            scrollbar-width:thin; -webkit-overflow-scrolling:touch;
        }
        .emp-journey .ej-step {
            position:relative; flex:1 0 130px; max-width:210px; padding-right:16px;
        }
        .emp-journey .ej-step::before {
            content:''; position:absolute; top:6px; left:7px; right:-16px; height:2px;
            background:var(--border-soft);
        }
        .emp-journey .ej-step:last-child::before { display:none; }
        .emp-journey .ej-dot {
            display:block; width:14px; height:14px; border-radius:50%; margin-bottom:12px;
            position:relative; z-index:1;
            background:var(--surface); border:2px solid var(--border);
        }
        .emp-journey .ej-step.ej-join .ej-dot { border-color:var(--text-faint); }
        .emp-journey .ej-step.ej-orientasi .ej-dot { border-color:#b45309; }
        .emp-journey .ej-step.ej-kontrak .ej-dot { border-color:var(--brand); }
        .emp-journey .ej-step.ej-tetap .ej-dot { border-color:#047C54; }
        .emp-journey .ej-step.is-current .ej-dot {
            background:var(--ej-accent); border-color:var(--ej-accent);
            box-shadow:0 0 0 4px color-mix(in srgb, var(--ej-accent) 20%, transparent);
        }
        .emp-journey .ej-step-title { font-size:12px; font-weight:700; color:var(--text-heading); margin:0; line-height:1.3; }
        .emp-journey .ej-step-date { font-size:11px; color:var(--text-muted); margin:3px 0 0; line-height:1.45; }
        .emp-journey .ej-step-date .ej-arrow { color:var(--text-faint); margin:0 3px; }
        .emp-journey .ej-step-date .ej-meta { color:var(--text-faint); }

        /* Mobile: timeline jadi VERTIKAL — garis di sisi kiri, tiap tahap satu baris ke bawah. */
        @media (max-width:640px) {
            .emp-journey .ej-track { flex-direction:column; overflow-x:visible; padding:6px 0 2px; }
            .emp-journey .ej-step {
                flex:0 0 auto; max-width:none; padding:0 0 18px 26px;
            }
            .emp-journey .ej-step:last-child { padding-bottom:0; }
            .emp-journey .ej-step::before {
                top:7px; bottom:-7px; left:6px; right:auto; width:2px; height:auto;
            }
            .emp-journey .ej-step:last-child::before { display:none; }
            .emp-journey .ej-dot { position:absolute; top:0; left:0; margin:0; }
        }
    </style>
    <div class="card-surface emp-journey" style="margin-bottom:20px;--ej-accent:{{ $ejAccent }};">
        <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:14px;">
            <div style="min-width:0;">
                <p style="font-size:10.5px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-faint);margin:0 0 4px;">Status Kepegawaian</p>
                <h3 style="font-size:20px;font-weight:800;letter-spacing:-0.3px;line-height:1.2;margin:0;color:var(--ej-accent);">{{ $sm['headline'] }}</h3>
                @if ($sm['sub'])
                    <p style="font-size:13px;color:var(--text-muted);margin-top:6px;line-height:1.6;">
                        {{ $sm['sub'] }}
                        @if ($sm['ended'])
                            <span style="color:var(--text-faint);">·</span>
                            @if ($sm['ended']->isPast())
                                <span style="font-weight:700;color:#dc2626;">habis {{ $sm['ended']->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE) }} lalu</span>
                            @else
                                <span style="font-weight:600;color:var(--text-body);">sisa {{ now()->diffForHumans($sm['ended'], \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</span>
                            @endif
                        @endif
                    </p>
                @endif
            </div>
            <div class="ej-ribbon" aria-hidden="true">
                @foreach ($ejRibbon as $key => $lbl)
                    <span class="ej-seg {{ $ejCurRank >= $this->phaseRank($key) && $ejCurRank > 0 ? 'is-on on-'.$key : '' }}">{{ $lbl }}</span>
                @endforeach
            </div>
        </div>

        @if (! is_null($sm['progress']))
            <div class="ej-bar"><span style="width:{{ round($sm['progress'] * 100) }}%;"></span></div>
        @endif

        @if (count($journey['nodes']) > 1)
            <div class="ej-track">
                @foreach ($journey['nodes'] as $n)
                    <div class="ej-step ej-{{ $n['phase'] }} {{ $loop->last ? 'is-current' : '' }}">
                        <span class="ej-dot"></span>
                        <p class="ej-step-title">{{ $n['title'] }}</p>
                        <p class="ej-step-date">
                            {{ \Carbon\Carbon::parse($n['start'])->translatedFormat('d M Y') }}
                            @if ($n['end'])
                                <span class="ej-arrow">→</span>{{ \Carbon\Carbon::parse($n['end'])->translatedFormat('d M Y') }}
                            @endif
                            @if ($n['meta'])<span class="ej-meta"> · {{ $n['meta'] }}</span>@endif
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Aksi self-service sesuai status saat ini (pindah dari nav "Status Saya" 2026-09-08):
             Orientasi → ajukan penilaian orientasi. Kontrak → ajukan perpanjangan kontrak +
             pengangkatan karyawan tetap. Tetap → tak ada tombol. --}}
        @if ($isSelf)
            @php
                $eju = auth()->user();
                // Kontrak dianggap "ada aksi buat ditampilkan" kalau salah satu dari 2 tombolnya
                // eligible - bukan cuma isKontrakKaryawan() polos, biar strip aksi ini nggak
                // muncul kosong (border doang tanpa tombol) pas kontrak lagi belum waktunya
                // perpanjangan DAN belum cukup masa kerja buat pengangkatan tetap.
                $showKontrakActions = $eju->isEligibleForPerpanjanganKontrak() || $eju->isEligibleForPengangkatanTetap();
            @endphp
            @if ($eju->isOrientasiKaryawan() || $showKontrakActions)
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border-soft);">
                    @if ($eju->isOrientasiKaryawan())
                        <a href="{{ route('orientasi') }}" wire:navigate class="btn-primary" style="font-size:12.5px;"><i class="fa-solid fa-user-graduate"></i> Ajukan Penilaian Orientasi</a>
                    @endif
                    {{-- Perpanjangan Kontrak: cuma muncul dalam jendela lead-time sebelum kontrak
                    berakhir & belum pernah mengajukan buat kontrak aktif ini - sama persis kondisi
                    KontrakSayaPage::getCanStartNewProperty(). Pengangkatan Tetap: butuh masa kerja
                    minimal (setting kepegawaian, default 15 bulan). Lihat User::isEligibleForXxx(). --}}
                    @if ($eju->isEligibleForPerpanjanganKontrak())
                        <a href="{{ route('kontrak.saya') }}" wire:navigate class="btn-primary" style="font-size:12.5px;"><i class="fa-solid fa-file-signature"></i> Ajukan Perpanjangan Kontrak</a>
                    @endif
                    @if ($eju->isEligibleForPengangkatanTetap())
                        <a href="{{ route('sikartap') }}" wire:navigate class="btn-ghost" style="font-size:12.5px;"><i class="fa-solid fa-user-check"></i> Ajukan Pengangkatan Karyawan Tetap</a>
                    @endif
                </div>
            @endif
        @endif
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
    {{-- Dua tanggal SELALU berdampingan (grid 2 kolom + panah), dari desktop sampai HP: input diberi
    min-width:0 supaya bisa menyusut (tanpa itu input type=date memaksa lebar bawaan browser dan
    salah satunya turun ke baris baru di layar sempit). Style inline supaya tidak perlu build CSS. --}}
    <style>
        .kh-period-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .kh-period-icon { width: 34px; height: 34px; border-radius: 10px; background: var(--brand-soft, rgba(4,44,108,0.1)); color: var(--brand); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; }
        .kh-period-title { font-size: 13.5px; font-weight: 700; color: var(--text-heading); margin: 0; line-height: 1.2; }
        .kh-period-sub { font-size: 11.5px; color: var(--text-muted); margin: 3px 0 0; }
        .kh-period-fields { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); align-items: end; gap: 10px; max-width: 560px; }
        .kh-period-fields .form-input { width: 100%; min-width: 0; box-sizing: border-box; }
        .kh-period-sep { color: var(--text-faint); font-size: 12px; padding-bottom: 12px; }
        /* Desktop: kepala (ikon + judul + ringkasan rentang) di kiri, dua tanggal di kanannya - 1 baris. */
        @media (min-width: 768px) {
            .kh-period-body { display: flex; align-items: center; gap: 28px; }
            .kh-period-body .kh-period-head { margin-bottom: 0; min-width: 210px; flex-shrink: 0; }
            .kh-period-body .kh-period-fields { flex: 1; }
        }
        @media (max-width: 480px) {
            .kh-period-fields { gap: 6px; }
            .kh-period-fields .form-input { padding-left: 8px; padding-right: 8px; font-size: 13px; }
            .kh-period-sep { font-size: 10px; }
        }
    </style>
    <div class="card-surface" style="margin-bottom:20px;">
        <div class="kh-period-body">
        <div class="kh-period-head">
            <span class="kh-period-icon"><i class="fa-solid fa-calendar-days"></i></span>
            <div style="min-width:0;">
                <p class="kh-period-title">Periode</p>
                @php
                    $khFrom = $date_from ? \Carbon\Carbon::parse($date_from) : null;
                    $khTo = $date_to ? \Carbon\Carbon::parse($date_to) : null;
                @endphp
                <p class="kh-period-sub">
                    @if ($khFrom && $khTo)
                        {{ $khFrom->translatedFormat('d M Y') }} &ndash; {{ $khTo->translatedFormat('d M Y') }}
                    @else
                        Pilih rentang tanggal
                    @endif
                </p>
            </div>
        </div>
        <div class="kh-period-fields">
            <div>
                <label class="form-label">Dari Tanggal</label>
                <input type="date" class="form-input" wire:model.live="date_from" @if ($date_to) max="{{ $date_to }}" @endif>
            </div>
            <span class="kh-period-sep" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
            <div>
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" class="form-input" wire:model.live="date_to" @if ($date_from) min="{{ $date_from }}" @endif>
            </div>
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
                // Lembur sengaja tidak ditandai di kalender ini (2026-08-15) - cuma Cuti/Izin.
                $requestTypeMeta = [
                    'leave' => ['label' => 'Cuti', 'color' => '#1a56c4'],
                    'permission' => ['label' => 'Izin', 'color' => '#d97706'],
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
        <!-- Section: Absensi (dulu "Kehadiran" - nama tab-nya sendiri tetap Kehadiran) -->
        <h3 style="font-size:14px;font-weight:800;color:var(--text-heading);margin-bottom:12px;">Absensi</h3>

        {{-- 3 kartu sama seperti Beranda: Sisa Cuti Tahunan + Izin + Lembur (attendance-stat-cards
             render 2 kartu). Cuti Sakit & kartu hitung "Cuti" lama dibuang (instruksi user). --}}
        {{-- 3 kolom seperti Beranda (kalau ada kartu Extra Shift: 2 kolom di HP, 4 di desktop): HP pakai ukuran compact (.bento-side-stats-compact,
             dari app.css), >=768px ukuran penuh (.attendance-summary-grid, inline di sini). --}}
        <style>
            .attendance-summary-grid { display: none; }
            @media (min-width: 768px) { .attendance-summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; } }
        </style>
        {{-- Karyawan berjadwal Shift punya 4 kartu (ada Extra Shift): HP 2 kolom, desktop 4 kolom;
             tanpa itu 3 kolom (.summary-cols-4 didefinisikan di x-attendance-summary-cards). --}}
        @php $summaryClass = $record->hasShiftSchedule() ? ' summary-cols-4' : ''; @endphp
        <div class="bento-side-stats-compact{{ $summaryClass }}">
            <x-attendance-summary-cards :compact="true" :user-id="$record->id" />
        </div>
        <div class="attendance-summary-grid{{ $summaryClass }}">
            <x-attendance-summary-cards :user-id="$record->id" />
        </div>

        <!-- Riwayat Pengajuan (dipindah ke bawah Kehadiran, bukan tab terpisah lagi) -->
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
                            <tr><td colspan="5" class="text-center text-sm text-slate-500 py-6">Tidak ada pengajuan yang disetujui pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
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
                    <a href="{{ route('profil-saya.penggajian.show', $payroll->id) }}" wire:navigate class="pwa-list-row" style="text-decoration:none;">
                        <div class="pwa-tile-icon warn"><i class="fa-solid fa-money-check-dollar"></i></div>
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">
                                {{ $payroll->payrollPeriod->start_date->translatedFormat('d M Y') }} - {{ $payroll->payrollPeriod->end_date->translatedFormat('d M Y') }}
                            </p>
                            @if ($payroll->payrollPeriod->payment_date)
                                <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                                    Dibayar {{ $payroll->payrollPeriod->payment_date->translatedFormat('d M Y') }}
                                </p>
                            @endif
                        </div>
                        <i class="fa-solid fa-chevron-right" style="color:var(--text-faint);font-size:12px;"></i>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endif

@if ($this->canViewPembinaanTab)
    {{-- Pembinaan — CUMA Coaching (pengembangan) di mana karyawan ini yang dibina. Counseling
    tidak ditampilkan di sini (instruksi user). Tampil buat karyawan itu sendiri, atasannya, atau
    pemegang SISTEM_PEMBINAAN_KARYAWAN. --}}
    <div x-show="tab === 'pembinaan'">
        @php $coachings = $this->myCoachings; @endphp

        <div class="pwa-list">
            @forelse ($coachings as $c)
                <a href="{{ route('coaching.show', $c) }}" wire:navigate class="pwa-list-row" style="align-items:flex-start;text-decoration:none;">
                    <div class="pwa-tile-icon teal"><i class="fa-solid fa-seedling"></i></div>
                    <div style="flex:1;min-width:0;">
                        <p class="pwa-row-title">{{ $c->title }}</p>
                        <span style="font-size:11.5px;color:var(--text-muted);">
                            Coach: {{ $c->coachBy?->name ?? '-' }}
                            @if ($c->improvement_due_date)
                                &middot; Deadline {{ \Carbon\Carbon::parse($c->improvement_due_date)->translatedFormat('d M Y') }}
                            @endif
                            &middot; {{ $c->created_at->translatedFormat('d M Y') }}
                        </span>
                    </div>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @empty
                <div class="pwa-list-row" style="opacity:0.7;">
                    <div style="flex:1;min-width:0;">
                        <p class="pwa-row-title">Belum ada coaching</p>
                        <span style="font-size:11.5px;color:var(--text-muted);">Riwayat coaching akan muncul di sini.</span>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
@endif

@if ($showMeritHistoryModal || $showAuditHistoryPanel)
    {{-- Panel geser (Riwayat Merit Point & Riwayat Perubahan Data): side panel nempel kanan di desktop,
    full-screen di HP (instruksi user 2026-09-25 - jangan pindah ke halaman/route terpisah). Beda pola
    dari modal-box biasa (yang di tengah) - dianimasikan translateX dari kanan, bukan translateY.
    Style-nya dipakai bersama kedua panel, jadi dirender kalau salah satunya terbuka. --}}
    <style>
        @keyframes merit-history-slide-x { from { transform: translateX(100%); } to { transform: translateX(0); } }
        @keyframes merit-history-fade { from { opacity: 0; } to { opacity: 1; } }
        .merit-history-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; animation: merit-history-fade 0.18s ease-out; }
        .merit-history-panel { position: fixed; top: 0; right: 0; bottom: 0; z-index: 10000; width: 420px; max-width: 100%; background: var(--surface); box-shadow: -8px 0 28px rgba(0,0,0,0.18); display: flex; flex-direction: column; animation: merit-history-slide-x 0.25s cubic-bezier(0.2, 0.8, 0.2, 1); }
        .merit-history-panel-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid var(--border-soft); flex-shrink: 0; }
        .merit-history-panel-body { flex: 1; overflow-y: auto; padding: 14px 20px calc(20px + env(safe-area-inset-bottom)); }
        @media (max-width: 767.98px) {
            .merit-history-panel { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            .merit-history-overlay, .merit-history-panel { animation: none; }
        }
    </style>
    @if ($showMeritHistoryModal)
    <div class="merit-history-overlay" wire:click="closeMeritHistoryModal"></div>
    <div class="merit-history-panel" role="dialog" aria-modal="true" aria-label="Riwayat Merit Point">
        <div class="merit-history-panel-header">
            <h3 style="font-size:15.5px;font-weight:700;color:var(--text-heading);margin:0;">Riwayat Merit Point</h3>
            <button type="button" wire:click="closeMeritHistoryModal" class="pwa-fullsheet-close" aria-label="Tutup">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="merit-history-panel-body">
            <div class="pwa-list">
                @forelse ($this->meritHistory as $tx)
                    <div class="pwa-list-row" style="align-items:flex-start;">
                        <div class="pwa-tile-icon {{ $tx->points >= 0 ? 'teal' : 'warn' }}">
                            <i class="fa-solid {{ $tx->points >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <p class="pwa-row-title">{{ $tx->rule?->name ?? 'Manual' }}</p>
                            <span style="font-size:11.5px;color:var(--text-muted);">{{ $tx->reason }}</span>
                            <br>
                            <span style="font-size:11px;color:var(--text-faint);">{{ $tx->created_at->translatedFormat('d M Y H:i') }}</span>
                        </div>
                        <span style="font-size:15px;font-weight:800;color:{{ $tx->points >= 0 ? '#047C54' : '#b91c1c' }};white-space:nowrap;">
                            {{ $tx->points >= 0 ? '+' : '' }}{{ $tx->points }}
                        </span>
                    </div>
                @empty
                    <div class="pwa-list-row" style="opacity:0.7;">
                        <div style="flex:1;min-width:0;">
                            <p class="pwa-row-title">Belum ada transaksi Merit Point</p>
                            <span style="font-size:11.5px;color:var(--text-muted);">Riwayat Merit Point Anda akan muncul di sini.</span>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    @if ($showAuditHistoryPanel && $this->canViewAuditHistory)
    {{-- Riwayat perubahan data akun/profil/pekerjaan — gabungan User + UserProfiles (lihat
    LogsFieldAuditChanges), mencatat siapa pun yang mengubah (karyawan sendiri via halaman ini,
    atau admin/HC lewat Kepegawaian/Filament) karena hook-nya di level model. Paginasi pakai
    Livewire (previousPage/nextPage 'riwayatPage'), BUKAN link wire:navigate, supaya panel tetap terbuka. --}}
    @php $auditHistory = $this->auditHistory; @endphp
    <div class="merit-history-overlay" wire:click="closeAuditHistoryPanel"></div>
    <div class="merit-history-panel" role="dialog" aria-modal="true" aria-label="Riwayat Perubahan Data">
        <div class="merit-history-panel-header">
            <h3 style="font-size:15.5px;font-weight:700;color:var(--text-heading);margin:0;">Riwayat Perubahan Data</h3>
            <button type="button" wire:click="closeAuditHistoryPanel" class="pwa-fullsheet-close" aria-label="Tutup">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="merit-history-panel-body">
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
                                    {{ $this->auditValue($entry, $entry->old_value) }}
                                    <i class="fa-solid fa-arrow-right" style="font-size:10px;color:var(--text-faint);margin:0 4px;"></i>
                                    {{ $this->auditValue($entry, $entry->new_value) }}
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

                @if ($auditHistory->hasPages())
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:14px;">
                        <button type="button" wire:click="previousPage('riwayatPage')" wire:loading.attr="disabled" class="btn-ghost btn-sm" @disabled($auditHistory->onFirstPage())>
                            <i class="fa-solid fa-chevron-left"></i> Sebelumnya
                        </button>
                        <span style="font-size:11.5px;color:var(--text-muted);">Halaman {{ $auditHistory->currentPage() }} dari {{ $auditHistory->lastPage() }}</span>
                        <button type="button" wire:click="nextPage('riwayatPage')" wire:loading.attr="disabled" class="btn-ghost btn-sm" @disabled(! $auditHistory->hasMorePages())>
                            Berikutnya <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                @endif
            @endif
        </div>
    </div>
    @endif
@endif

@if ($showMeritTransactionModal)
    {{-- Transisi buka modal - pola sama dengan modal Tugas Baru (task-list-page.blade.php):
    overlay fade + kotak naik sambil membesar dikit di desktop, slide dari bawah di HP. Scoped
    ke class sendiri (bukan .modal-overlay polos) supaya tidak ikut mengubah modal lain di
    halaman ini yang belum diminta pakai animasi ini. --}}
    <style>
        @keyframes merit-modal-fade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes merit-modal-pop { from { opacity: 0; transform: translateY(14px) scale(0.97); } to { opacity: 1; transform: none; } }
        @keyframes merit-modal-slide { from { transform: translateY(100%); } to { transform: none; } }
        .merit-modal-overlay { animation: merit-modal-fade 0.18s ease-out; }
        .merit-modal-overlay > .modal-box { animation: merit-modal-pop 0.22s cubic-bezier(0.2, 0.8, 0.2, 1); }
        @media (max-width: 767.98px) {
            .merit-modal-overlay > .modal-box { animation: merit-modal-slide 0.25s cubic-bezier(0.2, 0.8, 0.2, 1); }
        }
        @media (prefers-reduced-motion: reduce) {
            .merit-modal-overlay, .merit-modal-overlay > .modal-box { animation: none; }
        }
    </style>
    <div class="modal-overlay merit-modal-overlay" wire:click="closeModalMeritTransaction">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <div>
                    <h3 style="font-size:15px;font-weight:700;color:var(--text-heading);margin:0;">Transaksi Merit Point</h3>
                    <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">Karyawan: {{ $this->record->name }}</p>
                </div>
                <button type="button" wire:click="closeModalMeritTransaction" style="background:none;border:none;font-size:16px;color:var(--text-faint);cursor:pointer;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            {{-- Navigasi Tab (Berdasarkan Rule vs Manual) --}}
            <div style="display:flex;background:var(--surface-alt);padding:4px;border-radius:10px;gap:4px;margin-bottom:16px;">
                <button type="button" wire:click="$set('meritTab', 'rule')"
                    style="flex:1;padding:8px 12px;font-size:12.5px;font-weight:600;border:none;border-radius:8px;cursor:pointer;transition:all 0.2s;{{ $meritTab === 'rule' ? 'background:var(--surface);color:var(--text-heading);box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'background:transparent;color:var(--text-muted);' }}">
                    <i class="fa-solid fa-gavel" style="margin-right:6px;"></i> Berdasarkan Rule
                </button>
                <button type="button" wire:click="$set('meritTab', 'manual')"
                    style="flex:1;padding:8px 12px;font-size:12.5px;font-weight:600;border:none;border-radius:8px;cursor:pointer;transition:all 0.2s;{{ $meritTab === 'manual' ? 'background:var(--surface);color:var(--text-heading);box-shadow:0 1px 3px rgba(0,0,0,0.1);' : 'background:transparent;color:var(--text-muted);' }}">
                    <i class="fa-solid fa-pen-to-square" style="margin-right:6px;"></i> Manual
                </button>
            </div>

            <form wire:submit.prevent="saveMeritTransaction" style="display:flex;flex-direction:column;gap:14px;">
                @if ($meritTab === 'rule')
                    {{-- Tab 1: Berdasarkan Rule --}}
                    <div>
                        <label class="form-label">Pilih Aturan (Rule)</label>
                        <select wire:model.live="merit_rule_id" class="form-input">
                            <option value="">-- Pilih Aturan --</option>
                            @foreach ($this->meritPointRules as $rule)
                                <option value="{{ $rule->id }}">
                                    {{ $rule->name }} ({{ $rule->type?->getLabel() ?? $rule->type }} &bull; {{ $rule->points >= 0 ? '+' : '' }}{{ $rule->points }} Poin)
                                </option>
                            @endforeach
                        </select>
                        @error('merit_rule_id') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    @if ($merit_rule_id)
                        @php
                            $selectedRule = $this->meritPointRules->firstWhere('id', $merit_rule_id);
                        @endphp
                        @if ($selectedRule)
                            <div style="padding:10px 14px;background:var(--surface-alt);border-radius:8px;font-size:12px;display:flex;justify-content:space-between;align-items:center;">
                                <div>
                                    <span style="font-weight:600;color:var(--text-heading);">Tipe: {{ $selectedRule->type?->getLabel() }}</span>
                                </div>
                                <div>
                                    <span class="badge" style="font-size:13px;font-weight:700;background:{{ $selectedRule->points >= 0 ? '#dcfce7' : '#fee2e2' }};color:{{ $selectedRule->points >= 0 ? '#15803d' : '#b91c1c' }};">
                                        {{ $selectedRule->points >= 0 ? '+' : '' }}{{ $selectedRule->points }} Poin
                                    </span>
                                </div>
                            </div>
                        @endif
                    @endif

                    <div>
                        <label class="form-label">Keterangan / Catatan <span style="color:var(--danger-text);">*</span></label>
                        <textarea wire:model="merit_reason" class="form-input" rows="3" placeholder="Masukkan keterangan transaksi..."></textarea>
                        @error('merit_reason') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                @else
                    {{-- Tab 2: Manual --}}
                    <div>
                        <label class="form-label">Jenis Transaksi</label>
                        <div style="display:flex;gap:10px;">
                            <label style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:10px 14px;border:1px solid {{ $merit_manual_type === 'add' ? '#16a34a' : 'var(--border)' }};border-radius:8px;background:{{ $merit_manual_type === 'add' ? '#f0fdf4' : 'var(--surface)' }};cursor:pointer;">
                                <input type="radio" wire:model.live="merit_manual_type" value="add" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;">
                                <span style="font-size:13px;font-weight:600;color:{{ $merit_manual_type === 'add' ? '#15803d' : 'var(--text-body)' }};">
                                    <i class="fa-solid fa-plus-circle" style="margin-right:4px;"></i> Tambah Point (+)
                                </span>
                            </label>
                            <label style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:10px 14px;border:1px solid {{ $merit_manual_type === 'subtract' ? '#dc2626' : 'var(--border)' }};border-radius:8px;background:{{ $merit_manual_type === 'subtract' ? '#fef2f2' : 'var(--surface)' }};cursor:pointer;">
                                <input type="radio" wire:model.live="merit_manual_type" value="subtract" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;">
                                <span style="font-size:13px;font-weight:600;color:{{ $merit_manual_type === 'subtract' ? '#b91c1c' : 'var(--text-body)' }};">
                                    <i class="fa-solid fa-minus-circle" style="margin-right:4px;"></i> Kurangi Point (-)
                                </span>
                            </label>
                        </div>
                        @error('merit_manual_type') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Jumlah Poin <span style="color:var(--danger-text);">*</span></label>
                        <input type="number" wire:model="merit_points" min="1" max="100" class="form-input" placeholder="Contoh: 10">
                        @error('merit_points') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="form-label">Keterangan / Alasan <span style="color:var(--danger-text);">*</span></label>
                        <textarea wire:model="merit_reason" class="form-input" rows="3" placeholder="Masukkan keterangan (wajib diisi)..."></textarea>
                        @error('merit_reason') <p style="color:var(--danger-text);font-size:11.5px;margin-top:4px;">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div style="display:flex;gap:10px;margin-top:8px;">
                    <button type="submit" wire:loading.attr="disabled" class="btn-primary" style="flex:1;justify-content:center;">
                        <i class="fa-solid fa-check"></i> Simpan Transaksi
                    </button>
                    <button type="button" wire:click="closeModalMeritTransaction" class="btn-ghost" style="flex:1;justify-content:center;">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
</div>
