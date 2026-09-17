<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: 'Arial', sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; }
        .bg { position: fixed; top: 0; left: 0; width: 100%; }
        .content { position: relative; padding: 4.3cm 1.8cm 4.8cm 1.8cm; }
        h1.title { text-align: center; text-decoration: underline; font-size: 14px; margin: 0 0 16px; }
        table.info { width: 100%; margin-bottom: 14px; }
        table.info td { padding: 2px 0; vertical-align: top; }
        table.info td.label { width: 180px; }
        table.info td.sep { width: 14px; }
        table.comp { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.comp th, table.comp td { border: 1px solid #94a3b8; padding: 6px 8px; }
        table.comp thead th { background: #dbeafe; text-align: center; }
        table.comp td.komponen { width: 40%; }
        ul { margin: 4px 0; padding-left: 18px; }
        .section-title { font-weight: bold; margin-top: 10px; margin-bottom: 4px; }
        table.sign { width: 100%; margin-top: 40px; }
        table.sign td { width: 50%; vertical-align: top; }
        .sign-space { height: 60px; }
        .sign-space img { height: 55px; }
    </style>
</head>
<body>
    @if($background_path)
        <img class="bg" src="{{ $background_path }}">
    @endif

    <div class="content">
        <h1 class="title">OFFERING LETTER</h1>

        <table class="info">
            <tr><td class="label">Nama</td><td class="sep">:</td><td>{{ $full_name }}</td></tr>
            <tr><td class="label">Tempat / Tanggal Lahir</td><td class="sep">:</td><td>{{ $birth_place }}, {{ $birth_date }}</td></tr>
            <tr><td class="label">Alamat</td><td class="sep">:</td><td>{{ $domicile_address }}</td></tr>
            <tr><td class="label">Jabatan / Departemen</td><td class="sep">:</td><td>{{ $jabatan }}</td></tr>
            <tr><td class="label">Lokasi Kerja</td><td class="sep">:</td><td>{{ $lokasi_kerja }}</td></tr>
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
                        <td>{{ $row['orientasi'] > 0 ? 'Rp' . number_format($row['orientasi'], 0, ',', '.') : '' }}</td>
                        <td>{{ $row['kontrak'] > 0 ? 'Rp' . number_format($row['kontrak'], 0, ',', '.') : '' }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td class="komponen"><strong>Total</strong></td>
                    <td><strong>Rp{{ number_format(collect($compensation_rows)->sum('orientasi'), 0, ',', '.') }}</strong></td>
                    <td><strong>Rp{{ number_format(collect($compensation_rows)->sum('kontrak'), 0, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Benefit :</div>
        <ul>
            @foreach ($benefits as $benefit)
                <li>{{ $benefit['label'] }}{{ $benefit['is_starred'] ? '*' : '' }}</li>
            @endforeach
        </ul>
        @if ($benefit_note && collect($benefits)->contains('is_starred', true))
            <div>{{ $benefit_note }}</div>
        @endif

        <div class="section-title">Syarat &amp; Ketentuan Umum :</div>
        <ul>
            @foreach ($terms as $term)
                <li>{{ $term }}</li>
            @endforeach
        </ul>

        <table class="sign">
            <tr>
                <td>{{ $lokasi_kerja }}, {{ $tanggal_surat }}<br>Dibuat Oleh,</td>
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
                <td><u>{{ $signatory_name }}</u><br>{{ $signatory_title }}</td>
                <td><u>{{ $full_name }}</u><br>Calon Karyawan</td>
            </tr>
        </table>
    </div>
</body>
</html>
