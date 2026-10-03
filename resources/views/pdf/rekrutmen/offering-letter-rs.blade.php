<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { font-family: 'Arial', sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; }
        .bg { position: fixed; top: 0; left: 0; width: 100%; }
        .content { position: relative; padding: 4cm 3cm 3cm 4cm; }
        h1.title { text-align: center; text-decoration: underline; font-size: 14px; margin: 0 0 16px; }
        table.info { width: 100%; margin-bottom: 14px; }
        table.info td { padding: 2px 0; vertical-align: top; }
        table.info td.label { width: 160px; }
        table.info td.sep { width: 14px; }
        .section-title { font-weight: bold; margin-top: 10px; margin-bottom: 4px; }
        .justify { text-align: justify; }
        ul { margin: 4px 0; padding-left: 18px; }
        ul li { margin-bottom: 3px; }
        table.sign { width: 100%; margin-top: 40px; }
        table.sign td { width: 50%; vertical-align: top; }
        .sign-space { height: 60px; }
        .sign-space img { height: 55px; }
    </style>
</head>
<body>
    <img class="bg" src="{{ $background_path }}">

    <div class="content">
        <h1 class="title">SURAT PENAWARAN</h1>

        <table class="info">
            <tr><td class="label">Nama</td><td class="sep">:</td><td>{{ $full_name }}</td></tr>
            <tr><td class="label">Tempat / Tanggal Lahir</td><td class="sep">:</td><td>{{ $birth_place }}, {{ $birth_date }}</td></tr>
            <tr><td class="label">Alamat</td><td class="sep">:</td><td class="justify">{{ $domicile_address }}</td></tr>
            <tr><td class="label">Jabatan</td><td class="sep">:</td><td>{{ $jabatan }}</td></tr>
            <tr><td class="label">Lokasi Kerja</td><td class="sep">:</td><td>{{ $lokasi_kerja }}</td></tr>
        </table>

        <div class="section-title">A. KOMPENSASI</div>

        <div style="font-weight:bold;margin-bottom:2px;">Karyawan Mitra (3 Bulan)</div>
        <ul>
            @foreach ($mitra_items as $item)
                <li>{{ $item['label'] }}{{ filled($item['amount']) ? ' : ' . $item['amount'] : '' }}</li>
            @endforeach
        </ul>

        <div style="font-weight:bold;margin-bottom:2px;">Karyawan Kontrak (1 Tahun)</div>
        <ul>
            @foreach ($kontrak_items as $item)
                <li>{{ $item['label'] }}{{ filled($item['amount']) ? ' : ' . $item['amount'] : '' }}</li>
            @endforeach
        </ul>

        <div class="section-title">B. BENEFIT LAINNYA</div>
        <ul>
            @foreach ($benefits as $benefit)
                <li>{{ $benefit['label'] }}{{ $benefit['is_starred'] ? '*' : '' }}</li>
            @endforeach
        </ul>
        @if ($benefit_note && collect($benefits)->contains('is_starred', true))
            <div>{{ $benefit_note }}</div>
        @endif

        <div class="section-title">C. KETENTUAN UMUM</div>
        <ul class="justify">
            @foreach ($terms as $term)
                <li>{{ $term }}</li>
            @endforeach
        </ul>

        <table class="sign">
            <tr>
                <td>Banjarbaru, {{ $tanggal_surat }}<br></td>
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
                <td><u>{{ $signatory_name }}</u><br>{{ $signatory_title }}</td>
                <td><u>{{ $full_name }}</u><br>Calon Karyawan</td>
            </tr>
        </table>
    </div>
</body>
</html>
