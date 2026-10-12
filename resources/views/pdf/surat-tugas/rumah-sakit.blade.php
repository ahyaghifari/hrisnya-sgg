{{-- Template Surat Perintah Tugas (halaman 1) + Surat Perintah Perjalanan Dinas (halaman 2) untuk
     RUMAH SAKIT. $bgPath (kop) dan $logoUri disiapkan SuratTugasPdfService. --}}
@php
    $jabatan = fn ($user) => $user?->activeJobPosition?->jobPosition?->name ?? '-';
    $tanggalLines = collect($surat->periodeLinesSurat())->map(fn ($line) => e($line))->implode('<br>');
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
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.45;
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
            font-size: 12pt;
            margin: 6px 0 0;
        }

        .nomor {
            text-align: center;
            font-weight: bold;
            margin: 0 0 18px;
        }

        .info {
            margin: 6px 0 12px 8px;
        }

        .info td {
            padding: 1px 4px 1px 0;
            vertical-align: top;
        }

        .info td.label {
            width: 78px;
            white-space: nowrap;
        }

        .info td.sep {
            width: 10px;
        }

        table.peserta {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0 8px;
            font-size: 10.5pt;
        }

        table.peserta th,
        table.peserta td {
            border: 1px solid #000;
            padding: 1px 5px;
        }

        table.peserta th {
            text-align: center;
        }

        .rangka {
            text-align: justify;
            margin: 0 0 4px;
        }

        .penutup {
            margin: 12px 0 0;
            text-align: justify;
        }

        /* Blok tanda tangan murni alur biasa (tanpa posisi absolut), lihat holding.blade.php. */
        .sign-wrap {
            margin: 40px 0 0 52%;
        }

        .sign-block {
            display: inline-block;
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

        /* Halaman 2: Surat Perintah Perjalanan Dinas. */
        .spd-page {
            page-break-before: always;
        }

        .spd-nomor {
            text-align: center;
            margin: 0 0 18px;
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

        table.spd-form td.label {
            width: 45%;
        }

        .spd-tall {
            height: 95px;
        }
    </style>
</head>
<body>

<div class="bg-page"><img src="{{ $bgPath }}"></div>

{{-- ===================== HALAMAN 1: SURAT PERINTAH TUGAS ===================== --}}
<div class="title">SURAT PERINTAH TUGAS</div>
<div class="nomor">Nomor: {{ $surat->nomor ?: '-' }}</div>

<div>Yang bertanda tangan dibawah ini</div>
<table class="info">
    <tr><td class="label">Nama</td><td class="sep">:</td><td>{{ $surat->nama_ttd }}</td></tr>
    <tr><td class="label">NIP</td><td class="sep">:</td><td>{{ $surat->nip_ttd ?? $surat->ttdUser?->nip ?? '-' }}</td></tr>
    <tr><td class="label">Jabatan</td><td class="sep">:</td><td>{{ $surat->jabatan_ttd }}</td></tr>
</table>

<div>Dengan ini memberikan tugas kepada</div>
<table class="peserta">
    <thead>
        <tr><th>Nama</th><th>Jabatan</th><th style="width:90px;">NIP</th></tr>
    </thead>
    <tbody>
        @foreach ($surat->peserta as $peserta)
            <tr>
                <td>{{ $peserta->displayName() }}</td>
                <td>{{ $peserta->displayJabatan() ?? '-' }}</td>
                <td>{{ $peserta->displayNip() ?? '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="rangka">Dalam rangka {!! nl2br(e($surat->tugas)) !!} yang akan dilaksanakan pada</div>
<table class="info">
    <tr><td class="label">hari/tanggal</td><td class="sep">:</td><td>{!! $tanggalLines !!}</td></tr>
    <tr><td class="label">waktu</td><td class="sep">:</td><td>{{ $surat->waktu ?: '-' }}</td></tr>
    <tr><td class="label">tempat</td><td class="sep">:</td><td>{{ $surat->tempat }}</td></tr>
</table>

<div class="penutup">Demikian surat tugas ini diberikan kepada yang bersangkutan untuk dilaksanakan sebagaimana mestinya.</div>

@include('pdf.surat-tugas._rumah-sakit-ttd')

{{-- ===================== HALAMAN 2: SURAT PERINTAH PERJALANAN DINAS ===================== --}}
<div class="spd-page">
    <div class="title">SURAT PERINTAH PERJALANAN DINAS</div>
    <div class="spd-nomor">Nomor: {{ $surat->nomor ?: '-' }}</div>

    <table class="spd-form">
        <tr>
            <td class="label">Pejabat berwenang memberi perintah</td>
            <td>{{ $surat->jabatan_ttd }} {{ $surat->company?->letterName() }}</td>
        </tr>
        <tr>
            <td class="label">Nama karyawan yang diberi perintah</td>
            <td>
                @foreach ($surat->peserta as $peserta)
                    <div>{{ $peserta->displayName() }}</div>
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="label">Jabatan</td>
            <td>
                @foreach ($surat->peserta as $peserta)
                    <div>{{ $peserta->displayJabatan() ?? '-' }}</div>
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="label">Tempat yang dituju</td>
            <td>{{ $surat->tempat }}</td>
        </tr>
        <tr>
            <td class="label">Tujuan penugasan</td>
            <td>{!! nl2br(e($surat->tugas)) !!}</td>
        </tr>
        <tr>
            <td class="label">Hari/tanggal tugas</td>
            <td>{!! $tanggalLines !!}</td>
        </tr>
        <tr>
            <td class="label">Transportasi yang digunakan</td>
            <td>{{ $surat->transportasi ?: '' }}</td>
        </tr>
        <tr>
            <td class="label spd-tall">Nama, tanda tangan petugas dan stempel tempat yang dikunjungi</td>
            <td>&nbsp;</td>
        </tr>
    </table>

    @include('pdf.surat-tugas._rumah-sakit-ttd')
</div>

</body>
</html>
