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
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 20px;
        }

        .page {
            padding: 24px 28px;
            position: relative;
        }

        .header {
            height: 60px;
            border-radius: 0 0 40px 0;
            margin-bottom: 16px;
            padding: 12px 16px;
            color: #fff;
        }

        .company {
            font-size: 14px;
            font-weight: bold;
        }

        .stamp {
            position: absolute;
            top: 90px;
            left: 30px;
            border: 2px solid red;
            color: red;
            padding: 4px 10px;
            font-weight: bold;
            font-size: 14px;
            transform: rotate(-8deg);
        }

        .title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin: 10px 0 16px;
            font-size: 14px;
        }

        .info {
            display: table;
            width: 100%;
            margin-bottom: 12px;
        }

        .info .left,
        .info .right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .info table td {
            padding: 2px 4px;
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
            width: 60%;
        }

        table.salary .amount {
            width: 40%;
            text-align: right;
        }

        .section-title {
            font-weight: bold;
            margin-top: 6px;
        }

        .total-box {
            margin-top: 10px;
            border: 2px solid #000;
            padding: 8px;
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

        .footer {
            margin-top: 24px;
            font-size: 9px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="page">

    <div class="header">
        <div class="company">{{$payroll->period->company->name ?? '-'}}</div>
    </div>

    <div class="stamp">RAHASIA</div>

    <div class="title">SLIP GAJI</div>

    <!-- INFO -->
    <div class="info">
        <div class="left">
            <table>
                <tr><td>Nama Dokter</td><td>: {{ $payroll->doctor->name }}</td></tr>
                <tr><td>ID Dokter</td><td>: {{ $payroll->doctor->employee_number ?? '-' }}</td></tr>
                <tr><td>Status</td><td>: Dokter</td></tr>
            </table>
        </div>
        <div class="right">
            <table>
                <tr>
                    <td>Periode</td>
                    <td>:
                        {{ \Carbon\Carbon::parse($payroll->period->start_date)->format('d/m/Y') }}
                        -
                        {{ \Carbon\Carbon::parse($payroll->period->end_date)->format('d/m/Y') }}
                    </td>
                </tr>
                <tr>
                    <td>Tanggal Bayar</td>
                    <td>:
                        {{ $payroll->paid_at
                            ? \Carbon\Carbon::parse($payroll->paid_at)->format('d/m/Y')
                            : '-' }}
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="line"></div>

    <!-- SALARY -->
    <table class="salary">
        <tr>
            <td class="label">
                @foreach($payroll->items as $item)
                    {{ $item->service_name }}<br>
                @endforeach
            </td>
            <td class="amount">
                @foreach($payroll->items as $item)
                    Rp {{ number_format($item->amount, 0, ',', '.') }}<br>
                @endforeach
            </td>
        </tr>
    </table>

    <div class="line"></div>

    <!-- TOTAL -->
    <table class="salary">
        <tr>
            <td class="label"><strong>Total Bruto</strong></td>
            <td class="amount"><strong>Rp {{ number_format($payroll->gross_salary, 0, ',', '.') }}</strong></td>
        </tr>
        <tr>
            <td class="label">Pajak ({{ $payroll->tax_percentage }}%)</td>
            <td class="amount">Rp {{ number_format($payroll->tax_amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="total-box">
        <strong>Total Diterima</strong> :
        Rp {{ number_format($payroll->net_salary, 0, ',', '.') }}
    </div>

    <!-- SIGN -->
    <div class="signature">
        <div>
            Dibuat oleh,<br><br><br>
            <strong>NOOR AFINI, SE</strong><br>
            Accounting
        </div>
        <div>
            Diterima oleh,<br><br><br>
            <strong>Abdurahman Hakim</strong><br>
            Office Boy
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        (+62) 813-4957-9447 · info@syifaglobalgroup.com <br>
        Jl. R.O Ulin No. 93, Banjarbaru Selatan, Kalimantan Selatan 70712
    </div>

</div>

</body>
</html>
