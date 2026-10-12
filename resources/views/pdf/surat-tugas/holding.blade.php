{{-- Template Surat Perintah Tugas + Perjalanan Dinas untuk HOLDING. $bgPath (kop) dan $logoUri
     disiapkan SuratTugasPdfService (dipakai bersama template rumah-sakit). --}}
@php
    $jabatan = fn ($user) => $user?->activeJobPosition?->jobPosition?->name ?? '-';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Perintah Tugas {{ $surat->nomor }}</title>

    <style>
        /* Margin & background full-bleed mengikuti SP (kop ~3cm, footer ~2.5cm). */
        @page {
            margin: 3cm 2.5cm 2.5cm 2.5cm;
        }

        body {
            /* dompdf memetakan Arial ke Helvetica bawaan PDF (tanpa embed font). */
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt;
            line-height: 1.4;
            margin: 0;
        }

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

        .title {
            text-align: center;
            font-weight: bold;
            margin: 10px 0 0;
            font-size: 12pt;
        }

        /* Garis bawah judul pakai border (bukan text-decoration) karena ketebalan underline
           bawaan tidak bisa diatur di dompdf - contoh surat memakai garis lebih tebal. */
        .title-text {
            display: inline-block;
            border-bottom: 2px solid #000;
            line-height: 1.1;
        }

        /* Ukuran nomor = ukuran judul (.title), sama seperti contoh surat. */
        .nomor {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin: 0 0 20px;
        }

        .info {
            margin: 6px 0 8px 20px;
        }

        /* Jarak lebih lega di bawah "Yang bertanda tangan di bawah ini:" sebelum Nama/Jabatan. */
        .info-penanda-tangan {
            margin-top: 18px;
        }

        .info td {
            padding: 1px 4px 1px 0;
            vertical-align: top;
        }

        .info td.label {
            width: 90px;
            white-space: nowrap;
        }

        table.peserta {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 16px;
            font-size: 11pt;
        }

        table.peserta th,
        table.peserta td {
            border: 1px solid #000;
            padding: 1px 5px;
        }

        table.peserta th.no,
        table.peserta td.no {
            width: 28px;
            text-align: center;
        }

        .rangka {
            text-align: justify;
            margin: 0 0 6px;
        }

        .penutup {
            margin: 8px 0 0;
            text-align: justify;
        }

        /* Blok tanda tangan murni alur biasa (tanpa posisi absolut): kota/tanggal, perusahaan, QR,
           nama, NIP. .sign-wrap menggeser blok ke kanan, .sign-block menyusut selebar baris
           terpanjang (nama) supaya QR (rata tengah) berada tepat di tengah blok. */
        .sign-wrap {
            margin: 56px 0 0 55%;
        }

        .sign-block {
            display: inline-block;
        }

        .sign-name {
            text-decoration: underline;
        }

        .ttd-slot {
            height: 100px;
            text-align: center;
        }

        .verify-qr {
            width: 90px;
            height: 90px;
            margin-top: 5px;
        }

        /* Halaman 2: Surat Perintah Perjalanan Dinas (tabel), partial _holding-perjalanan-dinas. */
        .spd-page {
            page-break-before: always;
            font-size: 11pt;
            line-height: 1.5;
        }

        table.spd-form {
            width: 100%;
            border-collapse: collapse;
        }

        table.spd-form td {
            border: 1px solid #000;
            padding: 3px 7px;
            vertical-align: top;
        }

        table.spd-form td.no {
            width: 22px;
            text-align: center;
        }

        table.spd-form td.label {
            width: 195px;
        }

        .spd-tall-7 {
            height: 90px;
        }

        .spd-tall-8 {
            height: 110px;
        }
    </style>
</head>
<body>

<div class="bg-page"><img src="{{ $bgPath }}"></div>

<div class="title"><span class="title-text">SURAT PERINTAH TUGAS</span></div>
<div class="nomor">No. {{ $surat->nomor ?: '-' }}</div>

<div>Yang bertanda tangan di bawah ini:</div>
<table class="info info-penanda-tangan">
    <tr><td class="label">Nama</td><td>: {{ $surat->nama_ttd }}</td></tr>
    <tr><td class="label">Jabatan</td><td>: {{ $surat->jabatan_ttd }}</td></tr>
</table>

<div>Dengan ini memberikan tugas kepada:</div>
<table class="peserta">
    <thead>
        <tr><th class="no">No</th><th>Nama</th><th>Jabatan</th></tr>
    </thead>
    <tbody>
        @foreach ($surat->peserta as $peserta)
            <tr>
                <td class="no">{{ $loop->iteration }}</td>
                <td>{{ $peserta->displayName() }}</td>
                <td>{{ $peserta->displayJabatan() ?? '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="rangka">Dalam rangka {!! nl2br(e($surat->tugas)) !!} pada:</div>
<table class="info">
    {{-- Titik dua di kolom sendiri supaya baris tanggal berikutnya (beberapa periode) sejajar. --}}
    <tr><td class="label" style="width:140px;">Tanggal</td><td style="width:10px;">:</td><td>{!! collect($surat->periodeLinesSurat())->map(fn ($line) => e($line))->implode('<br>') !!}</td></tr>
    <tr><td class="label" style="width:140px;">Pukul</td><td style="width:10px;">:</td><td>{{ $surat->waktu ?: '-' }}</td></tr>
    <tr><td class="label" style="width:140px;">Tempat</td><td style="width:10px;">:</td><td>{{ $surat->tempat }}</td></tr>
</table>

<div class="penutup">Demikian Surat Tugas ini diberikan kepada yang bersangkutan untuk dilaksanakan sebagaimana mestinya.</div>

<div class="sign-wrap">
    <div class="sign-block">
        Banjarbaru, {{ ($tanggalSurat ?? now())->translatedFormat('d F Y') }}<br>
        PT Syifa Global Group
        @include('pdf.surat-tugas._ttd-stack')
        <span class="sign-name">{{ $surat->nama_ttd }}</span><br>
        NIP. {{ $surat->nip_ttd ?? $surat->ttdUser?->nip ?? '-' }}
    </div>
</div>

@include('pdf.surat-tugas._holding-perjalanan-dinas')

</body>
</html>
