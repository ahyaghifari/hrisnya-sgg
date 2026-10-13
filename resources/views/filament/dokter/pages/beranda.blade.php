@php
    $data = $this->daftar();
    $detail = $this->detail();
    $statusLabel = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];
@endphp
<x-filament-panels::page>
    {{-- CSS biasa (bukan Tailwind): panel ini tidak punya theme vite, jadi utility class kustom tidak ter-compile.
         Latar/bentuk/topbar ada di filament/dokter/background.blade.php. --}}
    <style>
        /* width:100% wajib: induknya flex-column dari Filament, tanpa ini pembungkus mengecil mengikuti isi (±860px). */
        .dk-wrap { position: relative; width: 100%; max-width: none; margin: 0; padding: 4vh 2rem 4rem; box-sizing: border-box; }
        /* Judul + cari + tambah dibatasi & di tengah; grid kartu di bawahnya tetap selebar layar. */
        .dk-hero { text-align: center; max-width: 46rem; margin: 0 auto; }
        .dk-head { display: flex; align-items: center; justify-content: center; gap: 1rem; }
        .dk-logo { width: 3.5rem; height: 3.5rem; border-radius: 9999px; background: rgba(255,255,255,.3); display: flex; align-items: center; justify-content: center; }
        .dk-logo svg { width: 1.75rem; height: 1.75rem; color: #fff; }
        .dk-title { margin: 0; font-size: 2.25rem; font-weight: 700; color: #fff; }
        .dk-sub { margin: .75rem auto 0; max-width: 36rem; font-size: .9375rem; line-height: 1.6; color: rgba(255,255,255,.92); }

        .dk-search { margin-top: 2rem; display: flex; align-items: center; background: rgba(255,255,255,.96); border-radius: 9999px; padding: .375rem;
            box-shadow: 0 10px 25px rgba(0,0,0,.12); box-sizing: border-box; }
        .dk-search svg { width: 1.25rem; height: 1.25rem; margin-left: .75rem; flex-shrink: 0; color: #6b7280; }
        .dk-search input { flex: 1; min-width: 0; border: 0; background: transparent; outline: none; box-shadow: none; padding: .5rem .75rem; font-size: .875rem; color: #1f2937; }
        .dk-search input:focus { box-shadow: none; outline: none; }
        .dk-btn { border: 0; cursor: pointer; border-radius: 9999px; background: #0f766e; color: #fff; padding: .5rem 1.5rem; font-size: .875rem; font-weight: 500; }
        .dk-btn:hover { background: #115e59; }
        @media (min-width: 768px) {
            .dk-search { padding: .375rem; }
            .dk-search svg { width: 1.375rem; height: 1.375rem; margin-left: 1rem; }
            .dk-search input { padding: .75rem 1rem; font-size: 1rem; }
            .dk-search .dk-btn { padding: .75rem 1.75rem; font-size: 1rem; }
        }

        .dk-listtitle { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
        .dk-add { display: inline-flex; align-items: center; gap: .375rem; height: 2.5rem; padding: 0 1rem; border-radius: 9999px; background: #0f766e; color: #fff;
            font-size: .8125rem; font-weight: 600; text-decoration: none; transition: background .15s; }
        .dk-add:hover { background: #115e59; }
        .dk-add svg { width: 1rem; height: 1rem; }

        .dk-listhead { margin-top: 7rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
        .dk-listhead h2 { margin: 0; font-size: 1.125rem; font-weight: 700; color: #fff; }
        .dk-filters { display: flex; flex-wrap: wrap; gap: .5rem; }
        .dk-sel { height: 2.5rem; padding: 0 2rem 0 .875rem; border: 1px solid rgba(255,255,255,.55); border-radius: 9999px; background-color: rgba(255,255,255,.22);
            color: #fff; font-size: .8125rem; font-weight: 500; cursor: pointer; outline: none; max-width: 16rem; }
        .dk-sel option { color: #1e293b; }

        .dk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem; }
        .dk-card { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 1.75rem 1.25rem; border: 0; cursor: pointer; font-family: inherit;
            background: rgba(255,255,255,.96); border-radius: 1rem; box-shadow: 0 4px 14px rgba(15,60,100,.12); transition: box-shadow .15s, transform .15s; }
        .dk-card:hover { box-shadow: 0 10px 24px rgba(15,60,100,.22); transform: translateY(-2px); }
        .dk-card:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }
        .dk-card.off { opacity: .65; }
        .dk-name { margin: 1rem 0 0; font-size: 1rem; font-weight: 700; color: #0f172a; }
        .dk-nip { margin: .25rem 0 0; font-size: .8125rem; color: #64748b; }
        .dk-tags { display: flex; flex-wrap: wrap; justify-content: center; gap: .375rem; margin-top: .875rem; }
        .dk-tag { padding: .2rem .625rem; border-radius: 9999px; background: #ccfbf1; color: #115e59; font-size: .75rem; font-weight: 600; }
        .dk-tag.gray { background: #f1f5f9; color: #475569; }
        .dk-empty { padding: 2rem; border-radius: 1rem; background: rgba(255,255,255,.96); text-align: center; color: #64748b; font-size: .875rem; }

        .dk-pager { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1.5rem; color: #fff; font-size: .8125rem; }
        .dk-pager button { border: 1px solid rgba(255,255,255,.55); background: rgba(255,255,255,.22); color: #fff; border-radius: 9999px; padding: .4rem 1rem; cursor: pointer; font-size: .8125rem; }
        .dk-pager button:disabled { opacity: .4; cursor: default; }

        /* Modal layar penuh */
        .dk-modal { position: fixed; inset: 0; z-index: 60; display: flex; flex-direction: column; background: #f8fafc; animation: dk-fade .18s ease-out; }
        @keyframes dk-fade { from { opacity: 0; } to { opacity: 1; } }
        .dk-mhead { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem; background: #0f766e; color: #fff; }
        .dk-mhead h3 { margin: 0; font-size: 1.25rem; font-weight: 700; }
        .dk-mhead small { display: block; opacity: .85; font-size: .8125rem; margin-top: .125rem; }
        .dk-mactions { margin-left: auto; display: flex; gap: .5rem; flex-shrink: 0; }
        .dk-mbtn { display: inline-flex; align-items: center; gap: .375rem; padding: .5rem 1rem; border-radius: 9999px; border: 1px solid rgba(255,255,255,.6);
            background: rgba(255,255,255,.15); color: #fff; font-size: .8125rem; font-weight: 600; cursor: pointer; text-decoration: none; }
        .dk-mbtn.solid { background: #fff; color: #0f766e; border-color: #fff; }
        .dk-mbtn:hover { background: rgba(255,255,255,.3); }
        .dk-mbtn.solid:hover { background: #ecfdf5; }
        .dk-mbtn svg { width: 1rem; height: 1rem; }
        .dk-mbody { flex: 1; overflow-y: auto; padding: 1.5rem; }
        .dk-cols { display: grid; gap: 1.25rem; grid-template-columns: 1fr; max-width: 72rem; margin: 0 auto; }
        @media (min-width: 900px) { .dk-cols { grid-template-columns: 1fr 1fr; } .dk-full { grid-column: 1 / -1; } }
        .dk-sec { background: #fff; border-radius: .875rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(15,60,100,.1); }
        .dk-sec h4 { margin: 0 0 .75rem; font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #0f766e; }
        .dk-dl { margin: 0; display: grid; grid-template-columns: 9rem 1fr; gap: .5rem .75rem; font-size: .875rem; }
        .dk-dl dt { color: #64748b; }
        .dk-dl dd { margin: 0; color: #0f172a; word-break: break-word; }
        .dk-doc { display: flex; align-items: center; gap: .75rem; padding: .625rem 0; border-top: 1px solid #f1f5f9; font-size: .875rem; }
        .dk-doc:first-of-type { border-top: 0; }
        .dk-doc .grow { flex: 1; min-width: 0; }
        .dk-doc .grow b { display: block; color: #0f172a; }
        .dk-doc .grow small { color: #64748b; }
        .dk-link { color: #0f766e; font-weight: 600; text-decoration: none; font-size: .8125rem; }
        .dk-link:hover { text-decoration: underline; }
        .dk-badge { padding: .125rem .5rem; border-radius: 9999px; font-size: .6875rem; font-weight: 700; background: #dcfce7; color: #166534; }
        .dk-badge.expired { background: #fee2e2; color: #991b1b; }
        .dk-badge.nonaktif { background: #f1f5f9; color: #475569; }
        .dk-badge.soon { background: #fef3c7; color: #92400e; }
    </style>

    <div class="dk-wrap">
        <div class="dk-hero">
            <div class="dk-head">
                <div class="dk-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
                <h1 class="dk-title">HRIS Dokter</h1>
            </div>
            <p class="dk-sub">Data dokter, spesialisasi, penempatan rumah sakit, dan dokumen profesional. Cari dokter berdasarkan nama, NIP, atau spesialisasi, atau tambahkan dokter baru beserta akun loginnya.</p>

            <form wire:submit="cari" class="dk-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" wire:model="q" placeholder="Cari nama, NIP, atau spesialisasi…" aria-label="Cari dokter" autofocus>
                <button type="submit" class="dk-btn">Cari</button>
            </form>
        </div>

        <div class="dk-listhead">
            <div class="dk-listtitle">
                <h2>Daftar Dokter ({{ $data['total'] }})
                    @if ($kataCari !== '') <span style="font-weight:500;opacity:.9;">— "{{ $kataCari }}"</span> @endif
                </h2>
                <a href="{{ $this->tambahUrl() }}" class="dk-add" title="Daftarkan dokter baru sekaligus buat akun HRIS-nya">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Dokter
                </a>
            </div>
            <div class="dk-filters">
                <select wire:model.live="filterSpesialisasi" class="dk-sel" aria-label="Filter spesialisasi">
                    <option value="">Semua spesialisasi</option>
                    @foreach ($this->spesialisasiOptions() as $id => $nama)
                        <option value="{{ $id }}">{{ $nama }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterStatus" class="dk-sel" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select>
            </div>
        </div>

        @if ($data['rows']->isEmpty())
            <div class="dk-empty">Tidak ada dokter yang cocok.</div>
        @else
            <div class="dk-grid">
                @foreach ($data['rows'] as $dokter)
                    <button type="button" wire:click="buka({{ $dokter->id }})" wire:key="dokter-{{ $dokter->id }}" class="dk-card {{ $dokter->status === 'nonaktif' ? 'off' : '' }}">
                        <x-dokter.avatar :photo="$data['foto'][$dokter->id] ?? null" :gender="$dokter->jenis_kelamin" :size="112" />
                        <p class="dk-name">{{ $dokter->nama_lengkap }}</p>
                        <p class="dk-nip">{{ $dokter->nip ?: 'NIP belum diisi' }}</p>
                        <div class="dk-tags">
                            @foreach ($dokter->specialties->take(2) as $spes)
                                <span class="dk-tag">{{ $spes->nama }}</span>
                            @endforeach
                            @if ($dokter->specialties->count() > 2)
                                <span class="dk-tag gray">+{{ $dokter->specialties->count() - 2 }}</span>
                            @endif
                            @if ($dokter->status === 'nonaktif')
                                <span class="dk-tag gray">Nonaktif</span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>

            @if ($data['terakhir'] > 1)
                <div class="dk-pager">
                    <button type="button" wire:click="ke({{ $data['halaman'] - 1 }})" @disabled($data['halaman'] <= 1)>‹ Sebelumnya</button>
                    <span>Halaman {{ $data['halaman'] }} dari {{ $data['terakhir'] }}</span>
                    <button type="button" wire:click="ke({{ $data['halaman'] + 1 }})" @disabled($data['halaman'] >= $data['terakhir'])>Berikutnya ›</button>
                </div>
            @endif
        @endif
    </div>

    @if ($detail)
        @php
            $d = $detail['doctor'];
            $user = $detail['user'];
        @endphp
        <div class="dk-modal" role="dialog" aria-modal="true" aria-label="Detail dokter" @keydown.escape.window="$wire.tutup()">
            <div class="dk-mhead">
                <x-dokter.avatar :photo="$detail['foto']" :gender="$d->jenis_kelamin" :size="56" />
                <div>
                    <h3>{{ $d->nama_lengkap }}</h3>
                    <small>{{ $d->nip ?: 'NIP belum diisi' }} · {{ $statusLabel[$d->status] ?? $d->status }}</small>
                </div>
                <div class="dk-mactions">
                    <a href="{{ $detail['editUrl'] }}" class="dk-mbtn solid">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Edit
                    </a>
                    <button type="button" wire:click="tutup" class="dk-mbtn" aria-label="Tutup">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        Tutup
                    </button>
                </div>
            </div>

            <div class="dk-mbody">
                <div class="dk-cols">
                    <section class="dk-sec">
                        <h4>Data Diri</h4>
                        <dl class="dk-dl">
                            <dt>Nama</dt><dd>{{ $d->nama_lengkap }}</dd>
                            <dt>NIP</dt><dd>{{ $d->nip ?: '-' }}</dd>
                            <dt>NIK</dt><dd>{{ $d->nik ?: '-' }}</dd>
                            <dt>Jenis kelamin</dt><dd>{{ ['L' => 'Laki-laki', 'P' => 'Perempuan'][$d->jenis_kelamin] ?? '-' }}</dd>
                            <dt>Tempat, tgl lahir</dt><dd>{{ collect([$d->tempat_lahir, $d->tanggal_lahir?->translatedFormat('j F Y')])->filter()->implode(', ') ?: '-' }}</dd>
                            <dt>Agama</dt><dd>{{ $d->agama ?: '-' }}</dd>
                        </dl>
                    </section>

                    <section class="dk-sec">
                        <h4>Akun HRIS</h4>
                        @if ($user)
                            <dl class="dk-dl">
                                <dt>Nama akun</dt><dd>{{ $user->name }}</dd>
                                <dt>Email</dt><dd>{{ $user->email }}</dd>
                                <dt>Status akun</dt><dd>{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</dd>
                            </dl>
                        @else
                            <p style="margin:0;color:#64748b;font-size:.875rem;">Belum ada akun HRIS yang tertaut. Tautkan lewat tombol Edit.</p>
                        @endif
                    </section>

                    <section class="dk-sec">
                        <h4>Spesialisasi</h4>
                        <div class="dk-tags" style="justify-content:flex-start;margin:0;">
                            @forelse ($d->specialties as $spes)
                                <span class="dk-tag">{{ $spes->nama }}</span>
                            @empty
                                <span style="color:#64748b;font-size:.875rem;">Belum diisi.</span>
                            @endforelse
                        </div>
                    </section>

                    <section class="dk-sec">
                        <h4>Penempatan Rumah Sakit</h4>
                        @forelse ($d->hospitals as $h)
                            <div class="dk-doc">
                                <div class="grow">
                                    <b>{{ $detail['rs'][$h->rumah_sakit_id] ?? 'RS tidak ditemukan' }}</b>
                                    <small>{{ $h->tanggal_mulai?->translatedFormat('j M Y') ?: '?' }} – {{ $h->tanggal_selesai?->translatedFormat('j M Y') ?: 'sekarang' }}</small>
                                </div>
                                <span class="dk-badge {{ $h->status === 'aktif' ? '' : 'nonaktif' }}">{{ $statusLabel[$h->status] ?? $h->status }}</span>
                            </div>
                            {{-- SIP terikat penempatan ini --}}
                            @foreach ($d->licenses->where('doctor_hospital_id', $h->id) as $sip)
                                @php $ss = $sip->status_efektif; @endphp
                                <div class="dk-doc" style="margin-left:1rem;">
                                    <div class="grow">
                                        <b>SIP{{ $sip->nomor ? ' · '.$sip->nomor : '' }}</b>
                                        <small>{{ $sip->tanggal_berlaku?->translatedFormat('j M Y') ?: '?' }} – {{ $sip->tanggal_berakhir?->translatedFormat('j M Y') ?: 'tanpa batas' }}</small>
                                    </div>
                                    <span class="dk-badge {{ $ss === 'aktif' ? '' : ($ss === 'akan_expired' ? 'soon' : ($ss === 'expired' ? 'expired' : 'nonaktif')) }}">{{ \App\Filament\Dokter\Resources\Doctors\RelationManagers\LicensesRelationManager::STATUS_LABELS[$ss] ?? $ss }}</span>
                                    @if ($sip->doctor_document_id)
                                        <a href="{{ route('filament.dokter.dokumen.show', $sip->doctor_document_id) }}" target="_blank" class="dk-link">Lihat</a>
                                    @endif
                                </div>
                            @endforeach
                        @empty
                            <p style="margin:0;color:#64748b;font-size:.875rem;">Belum ada penempatan.</p>
                        @endforelse
                    </section>

                    <section class="dk-sec dk-full">
                        <h4>Lisensi (STR / STR-KT)</h4>
                        {{-- SIP yang sudah punya penempatan tampil di bawah penempatannya; sisanya (data lama) tetap di sini. --}}
                        @forelse ($d->licenses->whereNull('doctor_hospital_id')->sortBy('jenis') as $lic)
                            @php $ls = $lic->status_efektif; @endphp
                            <div class="dk-doc">
                                <div class="grow">
                                    <b>{{ \App\Enum\Doctor\DoctorLicenseType::tryFrom($lic->jenis)?->getLabel() ?? $lic->jenis }}@if ($lic->nomor) · {{ $lic->nomor }}@endif</b>
                                    <small>{{ $lic->tanggal_berlaku?->translatedFormat('j M Y') ?: '?' }} – {{ $lic->tanggal_berakhir?->translatedFormat('j M Y') ?: 'tanpa batas' }}</small>
                                </div>
                                <span class="dk-badge {{ $ls === 'aktif' ? '' : ($ls === 'akan_expired' ? 'soon' : ($ls === 'expired' ? 'expired' : 'nonaktif')) }}">{{ \App\Filament\Dokter\Resources\Doctors\RelationManagers\LicensesRelationManager::STATUS_LABELS[$ls] ?? $ls }}</span>
                                @if ($lic->doctor_document_id)
                                    <a href="{{ route('filament.dokter.dokumen.show', $lic->doctor_document_id) }}" target="_blank" class="dk-link">Lihat</a>
                                @endif
                            </div>
                        @empty
                            <p style="margin:0;color:#64748b;font-size:.875rem;">Belum ada lisensi. Tambahkan lewat tombol Edit.</p>
                        @endforelse
                    </section>

                    <section class="dk-sec dk-full">
                        <h4>Credentialing</h4>
                        @forelse ($d->credentialingCases->sortByDesc('tanggal_pengajuan') as $case)
                            @php $ss = $case->spk_status; @endphp
                            <div class="dk-doc">
                                <div class="grow">
                                    <b>{{ $detail['rs'][$case->rumah_sakit_id] ?? 'RS tidak ditemukan' }}@if ($case->nomor) · {{ $case->nomor }}@endif</b>
                                    <small>{{ $case->tanggal_pengajuan?->translatedFormat('j M Y') ?: 'tanggal pengajuan belum diisi' }}
                                        @if ($case->spk_nomor) · SK SPK {{ $case->spk_nomor }}@endif
                                        @if ($case->spk_berakhir) · berlaku s.d. {{ $case->spk_berakhir->translatedFormat('j M Y') }}@endif</small>
                                </div>
                                @if ($ss)
                                    <span class="dk-badge {{ $ss === 'aktif' ? '' : ($ss === 'akan_expired' ? 'soon' : 'expired') }}">SPK {{ \App\Filament\Dokter\Resources\Doctors\RelationManagers\CredentialingRelationManager::SPK_LABELS[$ss] }}</span>
                                @endif
                                <span class="dk-badge nonaktif">{{ \App\Enum\Doctor\CredentialingStatus::tryFrom($case->status)?->getLabel() ?? $case->status }}</span>
                                @if ($case->doctor_document_id)
                                    <a href="{{ route('filament.dokter.dokumen.show', $case->doctor_document_id) }}" target="_blank" class="dk-link">PDF</a>
                                @endif
                            </div>
                        @empty
                            <p style="margin:0;color:#64748b;font-size:.875rem;">Belum ada riwayat credentialing. Tambahkan lewat tombol Edit.</p>
                        @endforelse
                    </section>

                    <section class="dk-sec dk-full">
                        <h4>Dokumen</h4>
                        @forelse ($d->documents->where('document_type', '!=', 'SIP')->sortByDesc('created_at') as $doc)
                            @php $st = $doc->status_efektif; @endphp
                            <div class="dk-doc">
                                <div class="grow">
                                    <b>{{ \App\Enum\Doctor\DoctorDocumentType::tryFrom($doc->document_type)?->getLabel() ?? \App\Enum\Doctor\DoctorLicenseType::tryFrom($doc->document_type)?->getLabel() ?? $doc->document_type }}</b>
                                    <small>{{ $doc->file_name }}@if ($doc->document_number) · No. {{ $doc->document_number }}@endif
                                        @if ($doc->expired_at) · berlaku s.d. {{ $doc->expired_at->translatedFormat('j M Y') }}@endif</small>
                                </div>
                                <span class="dk-badge {{ $st === 'aktif' ? '' : $st }}">{{ ucfirst($st) }}</span>
                                <a href="{{ route('filament.dokter.dokumen.show', $doc) }}" target="_blank" class="dk-link">Lihat</a>
                                <a href="{{ route('filament.dokter.dokumen.show', [$doc, 'download' => 1]) }}" class="dk-link">Unduh</a>
                            </div>
                        @empty
                            <p style="margin:0;color:#64748b;font-size:.875rem;">Belum ada dokumen. Unggah lewat tombol Edit.</p>
                        @endforelse
                    </section>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
