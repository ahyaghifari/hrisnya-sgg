@php
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

    // Unit Kerja = divisi/departemen jabatan aktif karyawan, reuse accessor yang sudah ada
    // (JobPositions::getDivisionOrUnitNameAttribute() - dipakai juga di modul Rekrutmen).
    $unitKerja = $sp->user->activeJobPosition?->jobPosition?->division_or_unit_name ?? '-';

    // Masa Kerja dari profile->joining_date s.d. tanggal SP - tahun+bulan, kalau belum setahun
    // cuma bulan (instruksi user 2026-09-28).
    // joining_date TIDAK di-cast Carbon di model (masih string) - parse manual di sini.
    $joiningDate = $sp->user->profile?->joining_date ? \Carbon\Carbon::parse($sp->user->profile->joining_date) : null;
    $masaKerja = '-';
    if ($joiningDate) {
        $years = (int) $joiningDate->diffInYears($sp->start_date);
        $months = (int) $joiningDate->copy()->addYears($years)->diffInMonths($sp->start_date);
        $masaKerja = $years > 0 ? "{$years} tahun {$months} bulan" : "{$months} bulan";
    }

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
            margin: 3cm 2.5cm 3.5cm 2.5cm;
        }

        body {
            font-family: Calibri, sans-serif;
            font-size: 12pt;
            line-height: 1.5;
            margin: 0;
        }

        p{
            text-align: justify;
            margin-bottom: 0;
            margin-top: 0.5cm;
        }

        .section-title{
            font-weight: bold;
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

        #dasar-klarifikasi ol {
            list-style-type: none;
            margin: 0;
            padding: 0;
        }

        .uraian li div {
            text-align: justify !important;
        }

        .uraian li ul li {
            list-style-type: none;
            text-align: justify;
            padding: 0;
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
            margin-top: 0.5cm;
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
            font-size:10.5pt;
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
            width: 50px;
            height: 50px;
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

    <div class="title">BERITA ACARA KLARIFIKASI KARYAWAN</div>
    <div class="nomor">Nomor: {{ $sp->report->nomor ?? '-' }}</div>

    <p style="text-align: justify;margin-top:0.8cm;">Pada hari ini, {{ $sp->start_date->translatedFormat('l, d F Y') }}, bertempat di {{ $sp->company->name ?? '-' }}, kami yang bertanda tangan di bawah ini telah melakukan klarifikasi resmi terhadap seorang karyawan terkait dugaan pelanggaran ketentuan perusahaan sebagaimana diatur dalam Peraturan Perusahaan dan peraturan perundangundangan yang berlaku. </p>
    
    <p>Berita Acara Klarifikasi ini dibuat sebagai dokumen resmi perusahaan dan dapat dijadikan dasar pertimbangan manajemen dalam menentukan tindak lanjut sesuai dengan ketentuan yang berlaku. </p>
    
    <p class="section-title">I. Identitas Para Pihak</p>

    <p>A. Pihak Perusahaan</p>
    
    <div class="info">
        <table>
            <tr><td>Nama</td><td>: {{ $coach->name }}</td></tr>
            <tr><td>Jabatan</td><td>: {{ $jabatan($coach) }}</td></tr>
            <tr><td>ID Karyawan</td><td>: {{ $sp->user->nip ?? '-' }}</td></tr>
        </table>
    </div>

    <p>B. Pihak Karyawan</p>
    <div class="info">
        <table>
            <tr><td>Nama</td><td>: {{ $sp->user->name }}</td></tr>
            <tr><td>Jabatan</td><td>: {{ $jabatan($sp->user) }}</td></tr>
            <tr><td>ID Karyawan</td><td>: {{ $sp->user->nip ?? '-' }}</td></tr>
            <tr><td>Unit Kerja</td><td>: {{ $unitKerja }}</td></tr>
            <tr><td>Masa Kerja</td><td>: {{ $masaKerja }}</td></tr>
        </table>
    </div>

    <p class="section-title">II. Dasar Klarifikasi</p>
    <div id="dasar-klarifikasi">
        {!! $sp->report->dasar_klarifikasi !!}
    </div>

    <p class="section-title">III. Uraian Kronologi Perusahaan</p>
    <p>
        Berdasarkan hasil pemeriksaan awal dan data yang dimiliki perusahaan, diketahui adanya dugaan kejadian sebagai berikut: 
    </p>

    <ul class="uraian">
        <li>{!! $sp->report->kejadian !!}</li>
    </ul>

    <p>Dalam proses klarifikasi ini, karyawan yang bersangkutan telah diberikan kesempatan seluas-luasnya untuk menyampaikan penjelasan dan pembelaan diri. Adapun pernyataan karyawan adalah sebagai berikut:</p>

    <ul class="uraian">
        <li>{!! $sp->report->klarifikasi_parafrase ?: $sp->report->subject_clarification !!}</li>
    </ul>

    <p style="margin-top: 0.1cm;">Karyawan menyatakan bahwa seluruh keterangan yang disampaikan adalah benar, diberikan tanpa adanya paksaan dari pihak mana pun, serta bersedia mempertanggungjawabkannya secara hukum dan ketenagakerjaan.</p>

    <p class="section-title">IV. Analisa & Kesimpulan Sementara</p>
    <p>Berdasarkan hasil klarifikasi tersebut, perusahaan menyimpulkan sementara bahwa : </p>
    <p style="font-weight:bold;">Analisa :</p>
    <div style="text-align: justify;">
        {!! $sp->report->analysis !!}
    </div>
    <p style="font-weight:bold;">Kesimpulan Sementara :</p>
    <div style="text-align:justify;">
        {!! $sp->report->kesimpulan !!}
    </div>

    <p>Kesimpulan ini bersifat sementara dan tidak menutup kemungkinan adanya perubahan berdasarkan hasil pemeriksaan lanjutan atau temuan bukti tambahan.</p>

    <p class="section-title">V. Rencana Tindak Lanjut</p>
    <p>Sehubungan dengan hasil klarifikasi ini, perusahaan akan melakukan langkah - langkah sebagai berikut: </p>
    <ul class="uraian">
        <li>
            {!! $sp->report->followup_plan !!}
        </li>
    </ul>

    <p class="section-title">VI. Penutup</p>
    <p>Demikian Berita Acara Klarifikasi ini dibuat dan ditandatangani dalam keadaan sadar, tanpa paksaan, serta untuk dipergunakan sebagaimana mestinya sebagai dokumen resmi perusahaan. <br><br> Apabila di kemudian hari terbukti bahwa keterangan yang disampaikan tidak benar, maka karyawan bersedia menerima konsekuensi sesuai dengan peraturan perusahaan dan ketentuan hukum yang berlaku. </p>

    <div style="margin-top: 1cm;">
        <center>Banjarbaru, {{ $sp->start_date->translatedFormat('d F Y') }}</center>
        <center>Ditandangani Oleh,</center>

        <div class="sign-block">
            <div class="sign-col">
                Pihak Perusahaan <br>
                @if ($verification['created_by'] ?? null)
                    <div class="verify-block">
                        <img src="{{ $verification['created_by']['qr'] }}" alt="QR Verifikasi" class="verify-qr">
                        <div class="verify-code">{{ $verification['created_by']['code'] }}</div>
                    </div>
                @else
                    <span class="ttd-space">&nbsp;</span>
                @endif
                <span class="sign-name">{{ $coach?->name ?? '-' }}</span><br>
                {{ $jabatan($coach) }}
            </div>
            {{-- QR hanya kalau karyawan sudah menandatangani BA (alur baru, instruksi user
            2026-10-01); BA lama tetap tanda tangan basah. --}}
            <div class="sign-col">
                Pihak Karyawan <br>
                @if ($verification['employee'] ?? null)
                    <div class="verify-block">
                        <img src="{{ $verification['employee']['qr'] }}" alt="QR Verifikasi" class="verify-qr">
                        <div class="verify-code">{{ $verification['employee']['code'] }}</div>
                    </div>
                @else
                    <span class="ttd-space">&nbsp;</span>
                @endif
                <span class="sign-name">{{ $sp->user->name ?? '-' }}</span><br>
                {{ $jabatan($sp->user)}}
            </div>
            <div class="sign-col">
                Mengetahui,
                @if ($verification['knows_by'] ?? null)
                    <div class="verify-block">
                        <img src="{{ $verification['knows_by']['qr'] }}" alt="QR Verifikasi" class="verify-qr">
                        <div class="verify-code">{{ $verification['knows_by']['code'] }}</div>
                    </div>
                @else
                    <span class="ttd-space">&nbsp;</span>
                @endif
                <span class="sign-name">{{ $sp->knowsBy?->name ?? '-' }}</span><br>
                {{ $jabatan($sp->knowsBy) }}
            </div>
        </div>
    </div>

</div>

</body>
</html>
