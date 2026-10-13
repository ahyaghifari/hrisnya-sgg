@php
    $d = $app->data ?? [];
    $tgl = fn ($v) => filled($v) ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
    $url = fn (string $path) => route('filament.dokter.lamaran.berkas', ['application' => $app->id, 'path' => $path]);

    // Kelompok data per konteks: [judul => [label => nilai]]; baris kosong disembunyikan.
    $groups = [
        'Data Diri' => [
            'Nama lengkap' => $app->namaPelamar(),
            'NIK' => $d['nik'] ?? null,
            'Tempat, tanggal lahir' => collect([$d['tempat_lahir'] ?? null, $tgl($d['tanggal_lahir'] ?? null)])->filter()->implode(', '),
            'Jenis kelamin' => $d['jenis_kelamin'] ?? null,
            'Agama' => $d['agama'] ?? null,
            'Status pernikahan' => $d['status_pernikahan'] ?? null,
            'Kewarganegaraan' => $d['kewarganegaraan'] ?? null,
            'Golongan darah' => $d['golongan_darah'] ?? null,
        ],
        'Kontak & Alamat' => [
            'No. HP / WhatsApp' => $d['no_hp'] ?? null,
            'Email' => $d['email'] ?? null,
            'Alamat KTP' => $d['alamat_ktp'] ?? null,
            'Alamat domisili' => $d['alamat_domisili'] ?? null,
            'Kota / kabupaten' => $d['dokter_city'] ?? null,
            'Provinsi' => $d['dokter_province'] ?? null,
        ],
        'Lamaran & Profesi' => [
            'Rumah sakit tujuan' => $app->company?->name,
            'Unit diminati' => $app->unit_minat,
            'Jenis dokter' => $d['jenis_dokter'] ?? ucfirst($app->jenis),
            'Spesialisasi' => $d['spesialisasi'] ?? null,
            'Subspesialisasi' => $d['subspesialisasi'] ?? null,
            'Status kepegawaian' => $d['status_kepegawaian'] ?? null,
        ],
        'Lisensi' => [
            'Nomor STR' => $d['str_nomor'] ?? null,
            'Kolegium / penerbit' => $d['str_kolegium'] ?? null,
            'STR terbit' => $tgl($d['str_terbit'] ?? null),
            'STR berlaku sampai' => $tgl($d['str_berlaku'] ?? null),
            'Nomor STR-KT' => $d['strkt_nomor'] ?? null,
            'STR-KT berlaku sampai' => $tgl($d['strkt_berlaku'] ?? null),
        ],
        'Rekening & Pajak' => [
            'NPWP' => $d['npwp'] ?? null,
            'Bank' => $d['nama_bank'] ?? null,
            'Nomor rekening' => $d['nomor_rekening'] ?? null,
            'Atas nama' => $d['atas_nama'] ?? null,
        ],
    ];

    // Riwayat: [judul, fn(baris) => judul kartu, fn(baris) => detail]
    $riwayat = [
        'pendidikan' => ['Pendidikan',
            fn ($r) => collect([$r['jenjang'] ?? null, $r['program'] ?? null])->filter()->implode(' · '),
            fn ($r) => [$r['institusi'] ?? null, ($r['tahun_masuk'] ?? '?').' – '.($r['tahun_lulus'] ?? '?'), isset($r['ipk']) ? 'IPK '.$r['ipk'] : null, $r['negara'] ?? null]],
        'pelatihan' => ['Pelatihan & Sertifikat',
            fn ($r) => $r['nama'] ?? '-',
            fn ($r) => [$r['penyelenggara'] ?? null, $tgl($r['tanggal'] ?? null), isset($r['skp']) ? $r['skp'].' SKP/jam' : null, isset($r['nomor']) ? 'No. '.$r['nomor'] : null, isset($r['berlaku']) ? 'berlaku s.d. '.$tgl($r['berlaku']) : null]],
        'pengalaman' => ['Pengalaman Kerja',
            fn ($r) => collect([$r['jabatan'] ?? null, $r['faskes'] ?? null])->filter()->implode(' · '),
            fn ($r) => [$r['jenis_faskes'] ?? null, ($tgl($r['mulai'] ?? null) ?? '?').' – '.($tgl($r['selesai'] ?? null) ?? 'sekarang'), isset($r['alasan']) ? 'Alasan berhenti: '.$r['alasan'] : null, isset($r['referensi']) ? 'Referensi: '.$r['referensi'] : null]],
    ];

    $fileLabels = [
        'kredensial' => 'Dokumen Kredensial', 'pas_foto' => 'Pas Foto', 'str_file' => 'Scan STR', 'strkt_file' => 'Scan STR-KT', 'd_cv' => 'CV', 'd_ktp' => 'KTP', 'd_npwp' => 'NPWP',
        'd_ijazah_dokter' => 'Ijazah Dokter', 'd_ijazah_spesialis' => 'Ijazah Spesialis', 'd_ijazah_subspesialis' => 'Ijazah Subspesialis',
        'd_transkrip' => 'Transkrip', 'd_kompetensi' => 'Sertifikat Kompetensi', 'd_rekening' => 'Buku Rekening',
    ];
@endphp
{{-- CSS biasa: panel dokter tidak punya theme vite, utility Tailwind kustom tidak ter-compile. --}}
<style>
    .ld { font-size: .875rem; color: #0f172a; }
    .ld h4 { margin: 0 0 .75rem; font-size: .75rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #0f766e; }
    .ld-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(24rem, 1fr)); gap: 1rem; margin-bottom: 1rem; }
    .ld-box { border: 1px solid #e2e8f0; border-radius: .75rem; padding: 1rem 1.25rem; background: #fff; }
    .ld-row { display: grid; grid-template-columns: 10rem 1fr; gap: .75rem; padding: .375rem 0; border-top: 1px solid #f1f5f9; }
    .ld-row:first-of-type { border-top: 0; }
    .ld-row dt { color: #64748b; }
    .ld-row dd { margin: 0; font-weight: 600; word-break: break-word; }
    .ld-hist { display: grid; grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr)); gap: .75rem; }
    .ld-hist div { border: 1px solid #e2e8f0; border-radius: .625rem; padding: .75rem 1rem; }
    .ld-hist b { display: block; }
    .ld-hist small { display: block; color: #64748b; margin-top: .125rem; }
    .ld-files { display: flex; flex-wrap: wrap; gap: .75rem; }
    .ld-file { display: flex; align-items: center; gap: .75rem; width: 15rem; padding: .75rem 1rem; border: 1px solid #e2e8f0; border-radius: .75rem;
        background: #fff; color: inherit; text-decoration: none; transition: border-color .15s, box-shadow .15s; }
    .ld-file:hover { border-color: #0f766e; box-shadow: 0 4px 12px rgba(15,118,110,.15); }
    .ld-file .ico { width: 2.5rem; height: 2.5rem; border-radius: .625rem; background: #ccfbf1; color: #0f766e; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .ld-file .ico svg { width: 1.375rem; height: 1.375rem; }
    .ld-file .txt { min-width: 0; }
    .ld-file b { display: block; font-size: .8125rem; }
    .ld-file small { display: block; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ld-muted { color: #64748b; margin: 0; }
</style>
<div class="ld">
    <div class="ld-grid">
        @foreach ($groups as $title => $rows)
            @php $rows = array_filter($rows, fn ($v) => filled($v)); @endphp
            @continue(! $rows)
            <section class="ld-box">
                <h4>{{ $title }}</h4>
                <dl style="margin:0;">
                    @foreach ($rows as $label => $value)
                        <div class="ld-row"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>

    @foreach ($riwayat as $key => [$title, $head, $detail])
        @if (! empty($d[$key]))
            <section class="ld-box" style="margin-bottom:1rem;">
                <h4>{{ $title }}</h4>
                <div class="ld-hist">
                    @foreach ($d[$key] as $row)
                        <div>
                            <b>{{ $head($row) }}</b>
                            <small>{{ collect($detail($row))->filter()->implode(' · ') }}</small>
                            @if (! empty($row['berkas']['path']))
                                <small><a href="{{ $url($row['berkas']['path']) }}" target="_blank" style="color:#0f766e;font-weight:600;">Lihat berkas</a></small>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    <section class="ld-box">
        <h4>Berkas</h4>
        @if (empty($app->files))
            <p class="ld-muted">Belum ada berkas diunggah.</p>
        @else
            <div class="ld-files">
                @foreach ($app->files as $key => $f)
                    <a href="{{ $url($f['path']) }}" target="_blank" class="ld-file">
                        <span class="ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg>
                        </span>
                        <span class="txt">
                            <b>{{ $fileLabels[$key] ?? \Illuminate\Support\Str::headline($key) }}</b>
                            <small>{{ $f['name'] }}</small>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
