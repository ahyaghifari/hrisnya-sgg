@php
    $titles = [
        'sp1' => 'SURAT PERINGATAN SATU (SP 1)',
        'sp2' => 'SURAT PERINGATAN DUA (SP 2)',
        'sp3' => 'SURAT PERINGATAN TIGA (SP 3)',
    ];

    // Background per company: file PRIVAT storage/app/private/sp-bg/{kode company}.jpg (bukan
    // public/ — dokumen internal), fallback ke default.jpg. Di-embed base64, pola persis
    // resources/views/pdf/payroll/payroll.blade.php.
    $bgDir = storage_path('app/private/sp-bg/');
    $bgFile = $bgDir . ($sp->company->code ?? '') . '.jpg';
    if (! is_file($bgFile)) {
        $bgFile = $bgDir . 'default.jpg';
    }
    $bgPath = is_file($bgFile) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($bgFile)) : '';

    $jabatan = fn ($user) => $user?->activeJobPosition?->jobPosition?->name ?? '-';

    // Kolom kiri tanda tangan = Coach (dari Coaching yang jadi sumber SP ini), BUKAN
    // pembuat/created_by (instruksi user 2026-09-25) - created_by bisa jadi staf HC-GA yang
    // sekadar entri data, beda orang dari coach yang mengawal karyawannya. Fallback ke
    // created_by kalau SP-nya MANUAL (tidak ada Coaching sumber, jadi tidak ada coach).
    $coach = $sp->coaching?->coachBy ?? $sp->createdBy;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $titles[$sp->sp_level->value] ?? 'SURAT PERINGATAN' }}</title>

    <style>
        /* Margin halaman = jarak aman konten dari kop (atas) & footer (bawah) di gambar
        background - berlaku OTOMATIS di SETIAP halaman lewat @page (beda dari spacer div biasa
        yang cuma sekali di awal dokumen lalu hilang begitu konten meluber ke halaman ke-2+, itu
        penyebab laporan user 2026-09-26: teks nabrak kop/footer di halaman kedua dst). Ukuran
        diukur dari gambar asli storage/app/private/sp-bg/*.jpg (1241x1755px ~ rasio A4): zona
        kop ~3cm, zona footer (yang ada footer-nya, mis. default.jpg) ~2.7cm. */
        @page {
            margin: 3cm 2.5cm 2.5cm 2.5cm;
        }

        body {
            font-family: Calibri, sans-serif;
            font-size: 12pt;
            line-height: 1.5;
            margin: 0;
        }

        /* Full-bleed: dorong balik pakai margin negatif sebesar @page margin di atas, supaya
        gambar tetap menutupi SELURUH halaman fisik (termasuk area kop/footer) meski @page sudah
        punya margin, sementara body teks tetap aman DI DALAM margin. Diulang dompdf identik di
        setiap halaman (position:fixed = running header/footer). */
        .bg-page {
            position: fixed;
            top: -3.3cm;
            left: -2.5cm;
            width: 21cm;
            height: 29.7cm;
            z-index: -1;
        }

        .bg-page img {
            width: 100%;
            height: 100%;
        }

        .page {
            position: relative;
        }

        .title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin: 10px 0 2px;
            font-size: 14pt;
        }

        .nomor {
            text-align: center;
            margin: 0 0 20px;
            font-size: 15px;
            font-weight: bold;
        }

        .intro {
            margin-bottom: 12px;
        }

        .info table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .info table td:first-child {
            width: 110px;
            white-space: nowrap;
        }

        .sehubungan {
            margin: 16px 0 8px;
        }

        .items {
            margin: 0.5cm 0 16px;
        }

        /* Hanging-indent pakai padding-left/text-indent (bukan display:table - bikin dompdf
        dorong seluruh item ke halaman baru kalau nggak muat pas, nyisain blank gede; bukan float
        juga - dompdf ngitung wrap-around float-nya salah, baris pertama jadi indent dobel).
        Isi item sekarang HTML dari Trix (WYSIWYG), tiap baris dibungkus <div> block yang bakal
        matahin hanging-indent kalau dibiarkan block - makanya div/p bawaan Trix dipaksa inline
        di bawah, biar nyambung 1 alur teks kayak sebelum ada WYSIWYG. */
        .items .item {
            margin-bottom: 0.5cm;
            padding-left: 18px;
            text-indent: -18px;
            text-align: justify;
        }

        .items .item div,
        .items .item p {
            display: inline;
        }

        .closing {
            margin: 16px 0 28px;
            text-align: justify;
        }

        .sign-block {
            display: table;
            width: 100%;
            margin-bottom: 32px;
        }

        .sign-col {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: top;
        }

        .sign-name {
            font-weight: bold;
            text-decoration: underline;
        }

        .ttd-space {
            display: block;
            height: 2cm;
        }

        .employee-sign {
            text-align: center;
            margin-top: 8px;
        }

        .verify-block {
            margin-top: 6px;
            text-align: center;
        }

        .verify-qr {
            width: 55px;
            height: 55px;
        }

        .verify-code {
            font-size: 7px;
            color: #555;
        }
    </style>
</head>
<body>

<div class="bg-page"><img src="{{ $bgPath }}"></div>

<div class="page">

    <div class="title">{{ $titles[$sp->sp_level->value] ?? 'SURAT PERINGATAN' }}</div>
    <div class="nomor">Nomor: {{ $sp->nomor ?? '-' }}</div>

    <div class="intro">Surat Peringatan ini ditujukan pada :</div>

    <div class="info">
        <table>
            <tr><td>Nama</td><td>: {{ $sp->user->name }}</td></tr>
            <tr><td>Jabatan</td><td>: {{ $jabatan($sp->user) }}</td></tr>
            <tr><td>NIP</td><td>: {{ $sp->user->nip ?? '-' }}</td></tr>
        </table>
    </div>

    <div class="sehubungan">Sehubungan dengan hal tersebut di bawah ini :</div>

    <div class="items">
        @forelse ($sp->items as $item)
            <div class="item">{{ $loop->iteration }}. {!! $item->text !!}</div>
        @empty
            <div class="item">-</div>
        @endforelse
    </div>

    <div class="closing">
        Demikian peringatan ini kami sampaikan, untuk kebaikan dan dijadikan acuan dalam melaksanakan tugas di perusahaan ini.
    </div>

    <div class="sign-block">
        <div class="sign-col">
            Banjarbaru, {{ $sp->start_date->translatedFormat('d F Y') }} <br>
            {{ $jabatan($coach) }}<br>
            @if ($verification['created_by'] ?? null)
                <div class="verify-block">
                    <img src="{{ $verification['created_by']['qr'] }}" alt="QR Verifikasi" class="verify-qr">
                    <div class="verify-code">{{ $verification['created_by']['code'] }}</div>
                </div>
            @else
                <span class="ttd-space">&nbsp;</span>
            @endif
            <span class="sign-name">{{ $coach?->name ?? '-' }}</span><br>
            {{ $coach?->nip ?? '-' }}
        </div>
        <div class="sign-col">
            Mengetahui,<br>
            {{ $jabatan($sp->knowsBy) }}<br>
            @if ($verification['knows_by'] ?? null)
                <div class="verify-block">
                    <img src="{{ $verification['knows_by']['qr'] }}" alt="QR Verifikasi" class="verify-qr">
                    <div class="verify-code">{{ $verification['knows_by']['code'] }}</div>
                </div>
            @else
                <span class="ttd-space">&nbsp;</span>
            @endif
            <span class="sign-name">{{ $sp->knowsBy?->name ?? '-' }}</span><br>
            {{ $sp->knowsBy?->nip ?? '-' }}
        </div>
    </div>

    {{-- QR karyawan HANYA tampil kalau karyawan benar-benar sudah menandatangani (SP dari BA alur
    baru, instruksi user 2026-10-01) - $verification['employee'] baru terisi kalau baris
    document_signatures peran 'employee' ada. SP lama tetap tanda tangan basah (ttd-space). --}}
    <div class="employee-sign">
        Karyawan
        @if ($verification['employee'] ?? null)
            <div class="verify-block">
                <img src="{{ $verification['employee']['qr'] }}" alt="QR Verifikasi" class="verify-qr">
                <div class="verify-code">{{ $verification['employee']['code'] }}</div>
            </div>
        @else
            <span class="ttd-space">&nbsp;</span>
        @endif
        <span class="sign-name">{{ $sp->user->name }}</span><br>
        {{ $sp->user->nip ?? '-' }}
    </div>

</div>

</body>
</html>
