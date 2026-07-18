<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji</title>

    <style>
        @page { margin: 0px;}

        body {
            background: url("{{ public_path('assets/salary_bg.jpg') }}") no-repeat;
            background-size: cover;
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
        }

        .page {
            padding: 24px 28px;
            position: relative;
        }

        .header {
            height: 60px;
            margin-bottom: 16px;
        }

        .header img {
            height: 100%;
        }

        .doc-header {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .doc-header .stamp-cell,
        .doc-header .period-cell {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .stamp {
            display: inline-block;
            border: 2px solid red;
            color: red;
            padding: 6px 14px;
            font-weight: bold;
            font-style: italic;
            font-size: 16px;
        }

        .period-cell table {
            margin-left: auto;
        }

        .period-cell td {
            padding: 1px 4px;
            font-size: 11px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin: 10px 0 16px;
            font-size: 16px;
        }

        .info table td {
            padding: 2px 4px;
        }

        .info table td:first-child {
            width: 130px;
            white-space: nowrap;
        }

        .line {
            border-top: 2px solid #000;
            margin: 8px 0;
        }

        table.salary {
            width: 100%;
            border-collapse: collapse;
        }

        table.salary td {
            padding: 3px 4px;
            vertical-align: top;
        }

        table.salary .label {
            width: 55%;
        }

        table.salary .colon {
            width: 2%;
        }

        table.salary .amount {
            width: 43%;
            text-align: left;
        }

        .section-title {
            font-weight: bold;
            margin-top: 6px;
            margin-bottom: 8px;
        }

        /* PENDAPATAN/POTONGAN tetap sebelahan (2 kolom halaman) — POTONGAN dikasih porsi lebih
           lebar (58%) karena punya 3 kolom (label, nominal, keterangan Nx/Bulan), PENDAPATAN
           cuma 2 kolom. Kolom nominal/keterangan dikasih lebar PASTI kecil (isinya pendek)
           supaya kolom label otomatis dapat SISA lebar terbanyak — jangan pakai width % di
           label, biar auto-layout tabel yang atur (biar label panjang mis. "Izin Terlambat
           Masuk / Pulang Cepat" tetap muat 1 baris meski kolomnya sendiri sudah dipersempit).
        */
        .salary-cols {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .salary-col {
            display: table-cell;
            vertical-align: top;
        }

        .salary-col.earnings {
            width: 42%;
            padding-right: 8px;
        }

        .salary-col.deductions {
            width: 58%;
            padding-left: 8px;
        }

        table.salary-items {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }

        table.salary-items td {
            padding: 2px 3px;
            white-space: nowrap;
        }

        table.salary-items .s-amount {
            width: 62px;
            text-align: left;
        }

        table.salary-items .s-note {
            width: 55px;
            text-align: right;
            font-size: 10px;
            color: #444;
        }

        .total-box {
            margin-top: 20px;
            width: 75%;
            border: 2px solid #000;
            padding: 8px;
        }

        .total-table {
            width: 100%;
            border-collapse: collapse;
        }

        .total-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .total-table .label {
            width: 30%;
            white-space: nowrap;
        }

        .total-table .colon {
            width: 2%;
        }

        .total-table .value {
            width: 68%;
        }

        .signature {
            margin-top: 28px;
            display: table;
            width: 100%;
            text-align: center;
        }

        .signature div {
            display: table-cell;
            width: 50%;
        }

        .ttd-space {
            display: block;
            height: 60px;
            line-height: 60px;
        }

        .footer {
            position: fixed;
            bottom: 32px;
            left: 48px;
            right: 48px;
            font-size: 9px;
            font-weight: bold;
        }

        .footer table {
            width: 100%;
        }

        .footer table td {
            padding: 1px 0;
        }
    </style>
</head>
<body>

<div class="page">

    <div class="header">
        <img src="{{ public_path('assets/logo-sgg.png') }}" alt="Logo">
    </div>

    @php
        // Nominal 0 cukup tampilkan "Rp" polos (tanpa "0") — diminta eksplisit biar slip tidak
        // penuh "Rp 0" berulang buat komponen yang memang tidak menghasilkan apa-apa.
        $rupiah = fn ($amount) => $amount > 0 ? 'Rp ' . number_format($amount, 0, ',', '.') : 'Rp';

        $joiningDate = $payroll->user->profile?->joining_date;
        $masaKerja = '-';

        if ($joiningDate) {
            $mulai = \Carbon\Carbon::parse($joiningDate);
            $sampai = \Carbon\Carbon::parse($payroll->payrollPeriod->end_date);
            $tahun = (int) $mulai->diffInYears($sampai);
            $bulan = $tahun > 0
                ? (int) $mulai->copy()->addYears($tahun)->diffInMonths($sampai)
                : (int) $mulai->diffInMonths($sampai);
            $masaKerja = $tahun > 0 ? "{$tahun} Tahun {$bulan} Bulan" : "{$bulan} Bulan";
        }
    @endphp

    <!-- HEADER: stamp RAHASIA (kiri) + Periode Gaji/Tanggal Bayar (kanan) -->
    <div class="doc-header">
        <div class="stamp-cell">
            <div class="stamp">RAHASIA</div>
        </div>
        <div class="period-cell">
            <table>
                <tr>
                    <td>Periode Gaji</td>
                    <td>:</td>
                    <td>{{ \Carbon\Carbon::parse($payroll->payrollPeriod->end_date)->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td>Tanggal Bayar</td>
                    <td>:</td>
                    <td>{{ $payroll->payrollPeriod->payment_date?->format('d/m/Y') ?? '-' }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="title">SLIP GAJI</div>

    <!-- INFO -->
    <div class="info">
        <table>
            <tr><td>Nama</td><td>: {{ $payroll->user->name }}</td></tr>
            <tr><td>Jabatan</td><td>: {{ $payroll->user->activeJobPosition->jobPosition->name ?? '-' }}</td></tr>
            <tr><td>NIP</td><td>: {{ $payroll->user->nip ?? '-' }}</td></tr>
            <tr><td>Masa Kerja</td><td>: {{ $masaKerja }}</td></tr>
            <tr><td>Status Karyawan</td><td>: {{ $payroll->user->profile?->employeeType?->name ?? '-' }}</td></tr>
        </table>
    </div>

    <div class="line"></div>

    <!-- SALARY: seluruh komponen gaji yang aktif di perusahaan ini (bukan cuma yang
    kebetulan menghasilkan angka) — komponen tanpa nilai tetap tampil Rp 0. Tetap sebelahan,
    POTONGAN dikasih porsi lebar lebih besar karena 3 kolom (lihat .salary-cols). -->
    <div class="salary-cols">
        <div class="salary-col earnings">
            <div class="section-title">PENDAPATAN</div>
            <table class="salary-items">
                @foreach($rows['earnings'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>:</td>
                        <td class="s-amount">{{ $rupiah($row['amount']) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>

        <div class="salary-col deductions">
            <div class="section-title">POTONGAN</div>
            <table class="salary-items">
                @foreach($rows['deductions'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>:</td>
                        <td class="s-amount">{{ $rupiah($row['amount']) }}</td>
                        <td class="s-note">{{ $row['note'] ? '(' . $row['note'] . ')' : '' }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>

    <div class="line"></div>

    <!-- TOTAL -->
    <table class="salary">
        <tr>
            <td class="label"><strong>TOTAL GAJI</strong></td>
            <td class="colon">:</td>
            <td class="amount"><strong>{{ $rupiah($payroll->gross_salary) }}</strong></td>
            <td class="label" style="padding-left: 28px;"><strong>TOTAL POTONGAN</strong></td>
            <td class="colon">:</td>
            <td class="amount"><strong>{{ $rupiah($payroll->total_deductions) }}</strong></td>
        </tr>
    </table>

    @php
        // NumberFormatter::SPELLOUT (ext-intl, locale id) sudah nerjemahin angka ke kata
        // Indonesia dengan benar bawaan — tidak perlu nulis ulang logic terbilang manual.
        $terbilang = ucfirst((new NumberFormatter('id', NumberFormatter::SPELLOUT))->format((int) round($payroll->net_salary))) . ' Rupiah';
    @endphp

    <div class="total-box">
        <table class="total-table">
            <tr>
                <td class="label"><strong>Total Gaji Diterima</strong></td>
                <td class="colon">:</td>
                <td class="value"><strong>{{ $rupiah($payroll->net_salary) }}</strong></td>
            </tr>
            <tr>
                <td class="label"><strong>Terbilang</strong></td>
                <td class="colon">:</td>
                <td class="value" style="font-style: italic;"><strong>{{ $terbilang }}</strong></td>
            </tr>
        </table>
    </div>

    <!-- SIGN -->
    <div class="signature">
        <div>
            Dibuat oleh,
            <span class="ttd-space">&nbsp;</span>
            <strong>{{ $payroll->payrollPeriod->generatedBy?->name ?? 'NOOR AFINI, SE' }}</strong><br>
            {{ $payroll->payrollPeriod->generatedBy?->activeJobPosition?->jobPosition?->name ?? 'Accounting' }}
        </div>
        <div>
            Diterima oleh,
            <span class="ttd-space">&nbsp;</span>
            <strong>{{ $payroll->user->name }}</strong><br>
            {{ $payroll->user->activeJobPosition->jobPosition->name ?? '-' }}
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <table>
            <tr><td colspan="2">(+62) 813-4957-9447</td></tr>
            <tr><td colspan="2">info@syifaglobalgroup.com</td></tr>
            <tr>
                <td>JL. R.O Ulin No. 93. Kec Banjarbaru Selatan</td>
                <td style="text-align: right;">www.syifaglobalgroup.com</td>
            </tr>
            <tr><td colspan="2">Kota Banjarbaru, Kalimantan Selatan, Indonesia 70712</td></tr>
        </table>
    </div>

</div>

</body>
</html>
