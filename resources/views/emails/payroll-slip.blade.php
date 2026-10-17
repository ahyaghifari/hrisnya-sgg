<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #1f2933; line-height: 1.7;">
    <p>
        Yang terhormat,<br>
        Bpk/Ibu {{ $payroll->employee?->nama_lengkap }}
    </p>

    <p>
        Dengan ini disampaikan Slip Gaji Anda pada Bulan {{ $payroll->payrollPeriod->end_date->translatedFormat('F Y') }}.<br>
        Mohon Cek Lampiran email berikut dengan password : 2 digit tanggal lahir Anda + 3 digit NIP terakhir.
    </p>

    <p>
        Salam Hormat,<br>
        {{ $senderName }}<br>
        {{ $senderPosition ?? '-' }}
    </p>
</body>
</html>
