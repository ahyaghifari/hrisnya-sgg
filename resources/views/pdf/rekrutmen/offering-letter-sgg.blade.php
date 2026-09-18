<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: 'Arial', sans-serif; font-size: 13px; color: #1a1a1a; margin: 0; }
        .bg { position: fixed; top: 0; left: 0; width: 100%; }
        .content { position: relative; padding: 4cm 3cm 3cm 4cm; }
        h1.title { text-align: center; text-decoration: underline; font-size: 14px; margin: 0 0 16px; }
        table.info { width: 100%; margin-bottom: 14px; }
        table.info td { padding: 2px 0; vertical-align: top; }
        table.info td.label { width: 180px; }
        table.info td.sep { width: 14px; }
        table.comp { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.comp th, table.comp td { border: 1px solid #94a3b8; padding: 6px 8px; }
        table.comp thead th { background: #b4c6e7; text-align: center; }
        table.comp td.komponen { width: 40%; }
        ul { margin: 0.3cm 0 12px; padding-left: 18px; }
        ul li { margin-bottom: 0.25cm; }
        .justify { text-align: justify; }
        .section-title { font-weight: bold; margin-top: 0.35cm; margin-bottom: 4px; }
        table.sign { width: 100%; margin-top: 0.8cm; table-layout: fixed; }
        table.sign td { width: 50%; vertical-align: top; }
        .sign-space { height: 1cm; }
        .sign-space img { height: 2cm; }
        .sign-title { display: block; margin-top: 6px; }
    </style>
</head>
<body>
    <img class="bg" src="{{ $background_path }}">

    <div class="content">
        <h1 class="title">OFFERING LETTER</h1>

        <table class="info">
            <tr><td class="label">Nama</td><td class="sep">:</td><td>{{ $full_name }}</td></tr>
            <tr><td class="label">Tempat / Tanggal Lahir</td><td class="sep">:</td><td>{{ $birth_place }}, {{ $birth_date }}</td></tr>
            <tr><td class="label">Alamat</td><td class="sep">:</td><td class="justify">{{ $domicile_address }}</td></tr>
            <tr><td class="label">Jabatan / Departemen</td><td class="sep">:</td><td>{{ $jabatan }}</td></tr>
            <tr><td class="label">Lokasi Kerja</td><td class="sep">:</td><td>PT Syifa Global Group, Banjarbaru</td></tr>
        </table>

        <div class="section-title">Kompensasi:</div>
        <table class="comp">
            <thead>
                <tr>
                    <th rowspan="2" class="komponen">Komponen</th>
                    <th colspan="2">Status Karyawan</th>
                </tr>
                <tr>
                    <th>Orientasi (3 bulan)</th>
                    <th>PKWT</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($compensation_rows as $row)
                    <tr>
                        <td class="komponen">{{ $row['label'] }}</td>
                        <td>{{ $row['orientasi'] }}</td>
                        <td>{{ $row['kontrak'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="justify">
            @php
                $benefitLabels = collect($benefits)->map(fn ($b) => $b['label'] . ($b['is_starred'] ? '*' : ''));
            @endphp
            <span class="section-title" style="margin-top:0;">Benefit Lain:</span>
            {{ $benefitLabels->count() > 1 ? $benefitLabels->slice(0, -1)->implode(', ') . ' & ' . $benefitLabels->last() : $benefitLabels->first() }}
        </div>
        @if ($benefit_note && collect($benefits)->contains('is_starred', true))
            <div style="margin-top: 0.2cm">{{ $benefit_note }}</div>
        @endif

        <div class="section-title">Syarat &amp; Ketentuan Umum :</div>
        <ul class="justify">
            @foreach ($terms as $term)
                <li>{{ $term }}</li>
            @endforeach
        </ul>

        <table class="sign">
            <tr>
                <td>Banjarbaru, {{ $tanggal_surat }}</td>
                <td></td>
            </tr>
            <tr>
                <td>Dibuat Oleh,</td>
                <td>Disetujui Oleh,</td>
            </tr>
            <tr>
                <td class="sign-space">
                    @if ($signatory_signature_path)
                        <img src="{{ $signatory_signature_path }}">
                    @endif
                </td>
                <td class="sign-space"></td>
            </tr>
            <tr>
                <td><u>{{ $signatory_name }}</u><span class="sign-title">{{ $signatory_title }}</span></td>
                <td><u>{{ $full_name }}</u><span class="sign-title">Calon Karyawan</span></td>
            </tr>
        </table>
    </div>
</body>
</html>
