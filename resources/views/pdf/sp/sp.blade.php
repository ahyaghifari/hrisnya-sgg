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
        @page { margin: 0px; }

        body {
            background: url("{{ $bgPath }}") no-repeat;
            background-size: cover;
            font-family: Arial, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }

        .page {
            padding: 24px 28px;
            position: relative;
        }

        /* Spacer atas — kop surat sudah jadi bagian gambar background. */
        .header {
            height: 100px;
            margin-bottom: 10px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin: 10px 0 4px;
            font-size: 19px;
        }

        .nomor {
            text-align: center;
            margin: 0 0 20px;
            font-size: 15px;
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
            margin: 0 0 16px;
        }

        .items .item {
            margin-bottom: 6px;
            padding-left: 18px;
            text-indent: -18px;
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
            height: 55px;
        }

        .employee-sign {
            text-align: center;
            margin-top: 8px;
        }
    </style>
</head>
<body>

<div class="page">

    <div class="header"></div>

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
            <div class="item">{{ $loop->iteration }}. {{ $item->text }}</div>
        @empty
            <div class="item">-</div>
        @endforelse
    </div>

    <div class="closing">
        Demikian peringatan ini kami sampaikan, untuk kebaikan dan dijadikan acuan dalam melaksanakan tugas di perusahaan ini.
    </div>

    <div class="sign-block">
        <div class="sign-col">
            Banjarbaru, {{ $sp->start_date->translatedFormat('d F Y') }}
            <span class="ttd-space">&nbsp;</span>
            {{ $jabatan($coach) }}<br>
            <span class="sign-name">{{ $coach?->name ?? '-' }}</span><br>
            {{ $coach?->nip ?? '-' }}
        </div>
        <div class="sign-col">
            Mengetahui
            <span class="ttd-space">&nbsp;</span>
            {{ $jabatan($sp->knowsBy) }}<br>
            <span class="sign-name">{{ $sp->knowsBy?->name ?? '-' }}</span><br>
            {{ $sp->knowsBy?->nip ?? '-' }}
        </div>
    </div>

    <div class="employee-sign">
        Karyawan
        <span class="ttd-space">&nbsp;</span>
        <span class="sign-name">{{ $sp->user->name }}</span><br>
        {{ $sp->user->nip ?? '-' }}
    </div>

</div>

</body>
</html>
