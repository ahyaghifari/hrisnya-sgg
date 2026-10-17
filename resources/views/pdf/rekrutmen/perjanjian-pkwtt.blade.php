<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* Mengikuti DRAFT PKWTT SGG.docx: A4, margin atas 3cm, lain 2,54cm, Arial 12pt. Background satu halaman (surat-bg). */
        @page { margin: 3cm 2.54cm 2.54cm 2.54cm; }
        body { font-family: 'Arial', sans-serif; font-size: 12pt; color: #000; margin: 0; line-height: 1.24; text-align: justify; }
        .bg { position: fixed; top: -3cm; left: -2.54cm; width: 21cm; height: 29.7cm; z-index: -1; }
        .center { text-align: center; }
        .p0 { margin: 0; }
        .p { margin: 0 0 8pt; }
        /* Judul pasal = tabel 2 kolom seperti draft: judul di kiri, kotak paraf kosong 2cm di kanan. */
        table.ph { width: 16.3cm; margin: 14pt 0 10pt -0.19cm; page-break-inside: avoid; }
        table.ph td.pt { width: 12.3cm; padding: 0 0 0 2cm; text-align: center; font-weight: bold; vertical-align: top; }
        table.ph td.pb { width: 2cm; height: 0.82cm; border: 1px solid #000; }
        /* RAHASIA di setiap halaman (header draft): kotak putih berbingkai merah tebal 3pt, 2,24 x 0,9cm. */
        .rahasia { position: fixed; top: -1.08cm; left: 15.09cm; width: 2.24cm; background: #fff; border: 3pt solid #f00; text-align: center; font-weight: bold; font-size: 10pt; line-height: 1.1; padding: 3pt 0; z-index: 5; }
        table { border-collapse: collapse; }
        table.info td, table.li td { text-align: justify; }
        table.info { width: 100%; }
        table.info td { padding: 0; vertical-align: top; }
        table.info td.no { width: 0.74cm; }
        table.info td.label { width: 4.75cm; }
        table.info td.sep { width: 0.5cm; }
        table.li { width: 100%; margin-bottom: 6pt; }
        table.li td { padding: 0; vertical-align: top; }
        table.li td.m { width: 0.75cm; }
        table.sign { width: 100%; page-break-inside: avoid; }
        table.sign td { width: 50%; vertical-align: top; text-align: center; padding: 0; }
        .sign-space { height: 62pt; }
    </style>
</head>
<body>
    @if ($background_path)
        <img class="bg" src="{{ $background_path }}">
    @endif
    <div class="rahasia">RAHASIA</div>

    @php
        $end = $start?->copy()->addMonths(3)->subDay();
        $dots = '...........................';
        $tgl = fn ($d, $fallback) => $d ? e($d->translatedFormat('d F Y')) : $fallback;

        // Daftar bernomor manual (dompdf tak bisa marker "1)" / "a)"). $items: teks, atau [teks, subdaftar].
        $list = function (array $items, string $style = 'a.', int $lvl = 0) {
            $o = '';
            foreach ($items as $i => $it) {
                $text = is_array($it) ? $it[0] : $it;
                $sub = is_array($it) ? ($it[1] ?? '') : '';
                $n = $style[0] === 'a' ? chr(97 + $i) : $i + 1;
                // Subdaftar di LUAR tabel induk: baris tabel tak bisa terpecah antar halaman, jadi subdaftar
                // panjang (mis. Pasal 12 butir a) memaksa seluruh butir lompat halaman.
                $o .= '<div style="padding-left:' . ($lvl * 0.75) . 'cm"><table class="li"><tr><td class="m">' . $n . substr($style, 1) . '</td><td>' . $text . '</td></tr></table>' . ($sub ? '<div style="padding-left:0.75cm">' . $sub . '</div>' : '') . '</div>';
            }

            return $o;
        };
    @endphp

    <div class="center p0" style="font-weight:bold;">SURAT PERJANJIAN KERJA</div>
    <div class="center" style="margin-bottom:12pt;">{{ $nomor }}</div>

    <div class="p0" style="margin-bottom:4pt;">Kami yang bertanda tangan di bawah ini:</div>
    <table class="info">
        <tr><td class="no">I.</td><td class="label">Nama</td><td class="sep">:</td><td><b>Muhammad Fauzul Akbar, S.M.</b></td></tr>
        <tr><td class="no"></td><td class="label">Jabatan</td><td class="sep">:</td><td><b>Direktur PT. Syifa Global Group</b></td></tr>
        <tr><td class="no"></td><td class="label">Alamat</td><td class="sep">:</td><td>Jl. RO Ulin No. 93, Kelurahan Banjarbaru Selatan, Kecamatan Banjarbaru Selatan, Kota Banjarbaru 70712</td></tr>
    </table>
    <div class="p0" style="margin:14pt 0 20pt;padding-left:0.75cm;">Dalam hal ini bertindak untuk dan atas PT. Syifa Global Group yang selanjutnya dalam Perjanjian ini disebut <b>PIHAK PERTAMA</b>.</div>

    <table class="info">
        <tr><td class="no">II.</td><td class="label">Nama</td><td class="sep">:</td><td>{{ $full_name }}</td></tr>
        <tr><td class="no"></td><td class="label">Jenis Kelamin</td><td class="sep">:</td><td>{{ $dots }}</td></tr>
        <tr><td class="no"></td><td class="label">Tanggal Lahir</td><td class="sep">:</td><td>{{ $birth_date }}</td></tr>
        <tr><td class="no"></td><td class="label">Status Perkawinan</td><td class="sep">:</td><td>{{ $marital ?: $dots }}</td></tr>
        <tr><td class="no"></td><td class="label">Alamat</td><td class="sep">:</td><td>{{ $address }}</td></tr>
        <tr><td class="no"></td><td class="label">Email</td><td class="sep">:</td><td>{{ $email }}</td></tr>
        <tr><td class="no"></td><td class="label">No. Telp</td><td class="sep">:</td><td>{{ $phone ?: $dots }}</td></tr>
        <tr><td class="no"></td><td class="label">No. KTP</td><td class="sep">:</td><td>{{ $nik }}</td></tr>
    </table>
    <div class="p" style="margin-top:12pt;">Dalam kedudukan masing-masing, kedua belah pihak telah setuju dan mufakat untuk mengadakan Perjanjian Kerja Waktu Tidak Tertentu, dengan syarat-syarat dan ketentuan-ketentuan yang diatur dalam pasal-pasal tersebut di bawah ini:</div>

    <table class="ph"><tr><td class="pt"><div>Pasal 1</div><div>PEMBERIAN TUGAS, PENGIKATAN DIRI,</div><div>DAN JANGKA WAKTU PERJANJIAN</div></td><td class="pb"></td></tr></table>
{!! $list([
        ['Sejak ditandatanganinya perjanjian kerja ini, PIHAK KEDUA menyatakan:', $list([
            'Mengikatkan diri sebagai pegawai tetap bagi PIHAK PERTAMA sesuai dengan pemberian tugas dari PIHAK PERTAMA.',
            'Tidak lagi terikat dengan perjanjian kerja Pihak lain.',
            'Bersedia untuk ditempatkan dimana saja yang ditunjuk oleh PIHAK PERTAMA sesuai ketentuan dari PIHAK PERTAMA.',
        ], '1)', 1)],
        ['PIHAK PERTAMA memberi penugasan kepada PIHAK KEDUA dan PIHAK KEDUA menerima penugasan dari PIHAK PERTAMA untuk melaksanakan pekerjaan sebagai berikut:'
            . '<table class="info" style="margin-top:2pt;"><tr><td class="label">Jabatan</td><td class="sep">:</td><td><b>' . e($jabatan) . '</b></td></tr>'
            . '<tr><td class="label">Unit Penempatan</td><td class="sep">:</td><td><b>' . e($unit ?: '-') . '</b></td></tr></table>'],
        'Dalam kondisi tertentu dan atas kesepakatan kedua belah pihak, maka PIHAK PERTAMA dapat memberi penugasan lain selain yang tersebut dalam Pasal 1 huruf b, tanpa mengurangi gaji dan fasilitas yang sudah diterima PIHAK KEDUA.',
        'Perjanjian Kerja Waktu Tidak Tertentu berlaku sejak tanggal ' . $tgl($start, '…………..') . ' Sampai dengan putusnya Perjanjian Kerja Waktu Tidak Tertentu sesuai yang diatur pada Pasal 12 tentang Pembatalan Perjanjian Kerja Dan Pengunduran Diri.',
        'PIHAK KEDUA menjalani masa percobaan selama 3 (tiga) bulan sejak tanggal ' . $tgl($start, '……………………') . ' sampai dengan tanggal ' . $tgl($end, '……………………...') . '.',
        'PIHAK PERTAMA akan menerbitkan Surat Keputusan Pengangkatan Karyawan Tetap setelah dilakukannya evaluasi atas masa percobaan pada Pasal 1 huruf (e) apabila PIHAK KEDUA dinyatakan berhasil atas masa percobaan tersebut.',
    ]) !!}

    <table class="ph"><tr><td class="pt"><div>Pasal 2</div><div>WAKTU KERJA</div></td><td class="pb"></td></tr></table>
{!! $list([
        ['PIHAK KEDUA wajib mengikuti ketentuan jam kerja yang telah ditentukan oleh PIHAK PERTAMA, yaitu:', $list([
            'Hari Senin s/d Jumat dari Pukul 08.30 WITA – 16.30 WITA',
            'Hari Sabtu dari Pukul 08.30 WITA – 13.30 WITA',
            ['Jam Istirahat, yaitu:', $list([
                'Senin s/d Kamis dari Pukul 12.00 WITA – 13.00 WITA',
                'Jumat dari Pukul 12.00 WITA – 14.00 WITA',
            ], 'a)', 2)],
        ], '1.', 1)],
        'Atas pertimbangan tertentu PIHAK PERTAMA dapat mengubah jadwal jam kerja atau hari kerja, dengan tetap mengacu pada perundang-undangan yang berlaku.',
    ]) !!}

    <table class="ph"><tr><td class="pt"><div>Pasal 3</div><div>HAK DAN KEWAJIBAN</div></td><td class="pb"></td></tr></table>
<div class="p"><b>PIHAK KEDUA BERHAK:</b></div>
    {!! $list([
        'Memperoleh upah sebagai imbalan dari kerja yang dilakukannya.',
        'Memperoleh Tunjangan Hari Raya (THR) dengan penghitungan sesuai peraturan perundang-undangan ketenagakerjaan yang berlaku.',
        'Memperoleh BPJS Ketenagakerjaan dan Dana Pensiun setelah menyelesaikan Masa Percobaan pada Pasal 1 huruf (e) dan telah ditetapkan dalam Surat Keputusan Pengangkatan Karyawan Tetap oleh PIHAK PERTAMA.',
        'Mengemukakan pendapat, usul, dan saran-saran kepada atasannya/manajemen.',
        'Memperoleh kesempatan untuk berkarya sesuai dengan keterampilan dan kompetensi di dalam rumah sakit.',
        'Melakukan pemutusan hubungan kerja sesuai ketentuan yang berlaku.',
    ]) !!}
    <div class="p"><b>PIHAK KEDUA BERKEWAJIBAN:</b></div>
    {!! $list([
        'Melaksanakan segala tugas dan kewajiban yang diberikan PIHAK PERTAMA sesuai dengan uraian tugas dengan penuh rasa tanggung jawab, jujur, penuh integritas, dan disiplin tinggi.',
        'Mematuhi segala Peraturan Perusahaan, tata tertib yang berlaku, dan segala petunjuk pelaksanaan pekerjaan dari PIHAK PERTAMA.',
        'Membudidayakan 5 (lima) S (Salam, Senyum, Sapa, Sopan dan Santun) selama berada di lingkungan kerja.',
        'Mengenakan pakaian yang pantas dan sopan sesuai dengan ketentuan PIHAK PERTAMA pada saat melaksanakan tugas dan pekerjaannya.',
        'Menggunakan identitas karyawan (ID Card) dan bersepatu tertutup.',
        'Mengikuti kegiatan rapat unit terkait dan kegiatan lainnya yang diselenggarakan PIHAK PERTAMA.',
        'Menjaga kerahasiaan perusahaan dan nama baik perusahaan selama bekerja dan/atau setelah tidak bekerja di PT. Syifa Global Group.',
        'Untuk menjamin pelaksanaan kewajiban PIHAK KEDUA kepada PIHAK PERTAMA berdasarkan Perjanjian ini, maka PIHAK KEDUA wajib menyerahkan jaminan berupa ijazah pendidikan terakhir asli kepada PIHAK PERTAMA atau jaminan dalam bentuk lain yang disetujui oleh PIHAK PERTAMA.',
    ]) !!}

    <table class="ph"><tr><td class="pt"><div>Pasal 4</div><div>MUTASI</div></td><td class="pb"></td></tr></table>
{!! $list([
        'PIHAK PERTAMA memiliki hak untuk memindahkan PIHAK KEDUA baik dalam jabatan dan/atau posisi yang sama dan/atau jabatan dan/atau posisi yang berbeda (mutasi) di dalam lingkup PT. Syifa Global Group tanpa mengurangi hak yang telah diterima oleh PIHAK KEDUA sebelumnya.',
        'Pemindahan yang dimaksud pada Pasal 4 huruf (a) akan dituangkan dalam Surat Keputusan Direktur PT. Syifa Global Group dan tidak menghapus hak dan kewajiban yang tertuang dalam Perjanjian Kerja Waktu Tidak Tertentu ini.',
        'Apabila PIHAK KEDUA melakukan perkawinan dan/atau memiliki ikatan darah dan/atau ikatan kekeluargaan dengan karyawan yang berada dalam lingkup PT. Syifa Global Group maka PIHAK PERTAMA berhak untuk memindahkan jabatan dan/atau posisi yang sama dan/atau jabatan dan/atau posisi yang berbeda (mutasi) ke bagian dan/atau divisi lain di lingkup PT. Syifa Global Group tanpa mengurangi hak yang telah diterima oleh PIHAK KEDUA sebelumnya.',
    ]) !!}

    <table class="ph"><tr><td class="pt"><div>Pasal 5</div><div>PERJANJIAN</div></td><td class="pb"></td></tr></table>
<div class="p">Selama masa Perjanjian Kerja, PIHAK KEDUA bersedia untuk:</div>
    {!! $list([
        'Tidak melakukan hal-hal yang menyimpang atau melanggar hukum, antara lain pencurian, manipulasi, penipuan, merokok di lingkungan kerja, pemanfaatan, penggunaan hak milik perusahaan tanpa hak atau ketidakjujuran yang akan merugikan perusahaan.',
        'Tidak meminta atau menerima pemberian berupa barang atau uang dari pihak ketiga yang berkaitan atau akan mempengaruhi pelaksanaan tugas.',
        'Menjaga nama baik PIHAK PERTAMA (tidak diperkenankan menyebarluaskan/ membocorkan rahasia perusahaan kepada media cetak, media elektronik atau kepada pihak yang tidak berkepentingan, atau segala suatu hal yang dianggap atau dapat dianggap rahasia oleh PIHAK PERTAMA dan dapat merugikan PIHAK PERTAMA).',
        'Tidak melakukan fitnah, ancaman, atau kekerasan pada sesama teman kerja atau atasan.',
        'Tidak menggunakan dan atau membawa obat terlarang, narkotika atau minuman keras baik di luar maupun di lingkungan kerja atau pada jam kerja.',
        'Tidak melakukan kelalaian atau kecerobohan kerja secara sengaja atau tidak sengaja sehingga mengakibatkan bahaya bagi sesama teman sekerja, atau kerugian bagi perusahaan.',
        'Tidak menggunakan fasilitas yang diberikan PIHAK PERTAMA untuk kepentingan pribadi selama jam kerja maupun di luar jam kerja.',
        'Tidak melawan atau menolak instruksi atasan atau PIHAK PERTAMA yang wajar dan masih berkaitan dengan bidang tugasnya.',
        'Tidak dalam kondisi hamil selama Masa Percobaan yang disebutkan pada Pasal 1 huruf (e)',
        'Tidak melanggar Peraturan Perusahaan, tata tertib kerja, dan prosedur kerja.',
        'Tidak melakukan pekerjaan rangkap di luar penugasan PIHAK PERTAMA.',
    ]) !!}

    <table class="ph"><tr><td class="pt"><div>Pasal 6</div><div>TATA TERTIB DAN SYARAT-SYARAT KERJA</div></td><td class="pb"></td></tr></table>
{!! $list([
        'Seluruh Karyawan harus menjaga nama baik perusahaan.',
        'Bekerja sesuai jadwal kerja dan konsistensi mengisi absensi pada saat masuk kerja dan pulang kerja secara pribadi serta tidak meninggalkan lingkungan kerja dengan alasan apapun tanpa sepengetahuan Penanggung jawab.',
        'Seluruh karyawan melaksanakan fingerprint pada mesin absensi saat hadir dan pulang. Toleransi keterlambatan hadir 5 menit terhitung jam hadir karyawan tersebut.',
        'Menggunakan identitas karyawan (ID Card) dan bersepatu tertutup.',
        'Melaksanakan ketentuan cara berpakaian yang rapi dengan seragam kerja yang sudah ditentukan oleh perusahaan.',
        'Berpenampilan rapi dan menarik dengan rambut dan kuku yang tertata dan tidak mengganggu dalam aktivitas kerja.',
        'Menjaga norma kesopanan serta menjaga kebersihan diri serta tidak merokok di dalam area kerja selama beraktivitas sesuai uraian tugas dan tanggung jawabnya.',
        'Melaksanakan tugas dan tanggung jawabnya sesuai dengan prosedur dan SOP yang berlaku efektif di bagian kerjanya.',
        'Tidak diperkenankan membawa anak pada saat jam kerja.',
        'Dilarang mengunggah foto/video/status/hasil pemeriksaan/ apapun yang berisi informasi rahasia ke media sosial.',
        'Tidak menggunakan alat komunikasi seluler pribadi dan/atau penggunaan internet pada unit komputer untuk kepentingan pribadi yang mengganggu produktivitas kerja selama jam kerja pada saat melaksanakan tugas dan tanggung jawabnya.',
        'Jika karyawan tidak melaksanakan ketentuan pada pasal ini maka akan diberikan sanksi berupa teguran lisan sampai dengan surat peringatan.',
        'Bahwa apa yang dihasilkan oleh PIHAK KEDUA selama hubungan kerja berlangsung adalah merupakan bagian dan Hak Kekayaan Intelektual (HKI) milik PIHAK PERTAMA sepenuhnya. Oleh karenanya, PIHAK KEDUA dilarang membuat/memperbanyak/meniru hal tersebut dalam bentuk dan alasan apapun tanpa izin tertulis dari PIHAK PERTAMA.',
    ]) !!}

    <table class="ph"><tr><td class="pt"><div>Pasal 7</div><div>IMBALAN JASA DAN TUNJANGAN LAIN</div></td><td class="pb"></td></tr></table>
{!! $list([
        'Sebagai imbalan atas pelaksanaan tugas dan pekerjaan PIHAK KEDUA, maka PIHAK PERTAMA telah menyetujui dan bersedia memberikan upah kepada PIHAK KEDUA yang sudah ditentukan besarannya oleh PIHAK PERTAMA, dan disetujui oleh PIHAK KEDUA.',
        'Imbalan terdiri dari Gaji Pokok, Tunjangan Jabatan dan Tunjangan Transportasi.',
        ['Selain imbalan jasa tersebut pada Pasal 6 huruf (a) perjanjian ini, PIHAK PERTAMA setuju untuk memberikan Tunjangan Hari Raya (THR), berdasarkan ketentuan yang berlaku mengenai pembayaran Tunjangan Hari Raya (THR) Idul Fitri dengan ketentuan sebagai berikut:', $list([
            'Jika PIHAK KEDUA telah memiliki masa kerja 12 (dua belas) bulan berturut-turut tanpa terputus, akan menerima THR sebesar 1 x gaji sebulan.',
            'Jika PIHAK KEDUA adalah karyawan yang di mutasi atau promosi maka PIHAK KEDUA tetap akan menerima THR sesuai dengan besaran yang diterima sebelumnya atau mengikuti Surat Penawaran Kerja yang telah di tanda tangani sebelumnya.',
            ['Jika PIHAK KEDUA telah memiliki masa kerja lebih dari 1 (satu) bulan tetapi kurang dari 1 (satu) tahun, maka PIHAK KEDUA akan menerima THR secara proporsional dengan perhitungan sebagai berikut:'
                . '<table style="margin:4pt 0 0 0;"><tr><td style="border-bottom:1px solid #000;text-align:center;padding:0 4pt;">Bulan masa kerja</td><td rowspan="2" style="vertical-align:middle;padding-left:4pt;">x 1 (satu) bulan upah pokok</td></tr><tr><td style="text-align:center;padding:0 4pt;">12</td></tr></table>'],
            'Jika PIHAK KEDUA memiliki masa kerja kurang dari 1 (satu) bulan, maka PIHAK KEDUA belum berhak/tidak mendapat THR.',
        ], '1)', 1)],
        'Imbalan jasa per bulan akan dibayarkan setiap tanggal 30 (tiga puluh) pada bulan tersebut.',
        'Imbalan jasa tersebut di atas akan dibayarkan oleh PIHAK PERTAMA kepada PIHAK KEDUA dengan cara transfer ke rekening Bank Syariah Indonesia yang dimiliki PIHAK KEDUA.',
    ]) !!}

    <table class="ph"><tr><td class="pt"><div>Pasal 8</div><div>LEMBUR</div></td><td class="pb"></td></tr></table>
<div class="p">Selain imbalan jasa sebagaimana tercantum dalam Pasal 7 huruf (a), huruf (b) dan huruf (c) Perjanjian ini, PIHAK KEDUA berhak mendapatkan bantuan fasilitas sesuai dengan Surat Keputusan No. 5 / E.1 / SGG / X / 2024 tentang Peraturan Perusahaan PT. Syifa Global Group.</div>

    <table class="ph"><tr><td class="pt"><div>Pasal 9</div><div>IZIN DAN CUTI PADA HARI KERJA</div></td><td class="pb"></td></tr></table>
{!! $list([
        'PIHAK KEDUA dapat mengajukan izin terlambat masuk atau pulang cepat hanya untuk alasan yang bersifat penting atau kejadian yang tidak diperkirakan sebelumnya maksimal 3 (tiga) jam dari jumlah jam per hari kerja dalam waktu yang tidak berturut-turut maksimal 2 (dua) kali dalam 1 (satu) bulan dan dengan persetujuan atasan/petugas yang ditunjuk PIHAK PERTAMA.',
        ['PIHAK PERTAMA memberikan hak cuti kepada PIHAK KEDUA sebanyak 12 (dua belas) hari kerja dengan ketentuan sebagai berikut:', $list([
            'Cuti timbul setelah PIHAK KEDUA telah menjalani masa kerja selama 12 (dua belas) bulan berturut-turut dari masuk bekerja sesuai dengan Pasal 1 huruf (e);',
            'PIHAK KEDUA yang berstatus mutasi atau promosi maka masa kerja pada jabatan dan/atau posisi sebelumnya terhitung sebagai masa kerja;',
            'Mendapatkan persetujuan dari Pimpinan;',
            'Cuti Bersama akan mengurangi hak cuti tahunan;',
            'Cuti tahunan tidak dapat diuangkan dan akan hangus apabila sampai akhir tahun tidak digunakan;',
            'Pengajuan cuti dilakukan oleh PIHAK KEDUA selambat-lambatnya 30 (tiga puluh) hari sebelum tanggal cuti yang akan diambil;',
            'Pemanfaatan hak cuti tahunan maksimal 5 (lima) hari kerja secara berturut-turut sedangkan sisa hari cuti dimanfaatkan di waktu yang lain.',
        ], '1)', 1)],
        'Ketentuan izin dan cuti pada hari kerja sesuai dengan Surat Keputusan No. 5 / E.1 / SGG / X / 2024 tentang Peraturan Perusahaan.',
    ]) !!}

    <table class="ph"><tr><td class="pt"><div>Pasal 10</div><div>SAKIT PADA HARI KERJA</div></td><td class="pb"></td></tr></table>
{!! $list([
        ['PIHAK KEDUA tidak masuk kerja karena sakit dengan sebab apapun maka harus menyerahkan surat keterangan dari dokter pemeriksa.', $list([
            'Surat keterangan sakit dari dokter umum maksimal 2 (dua) hari tidak masuk kerja;',
            'Surat keterangan sakit dari dokter spesialis maksimal 3 (tiga) hari tidak masuk kerja atau sesuai dengan diagnosis dari dokter spesialis;',
            'Jika sakit lebih dari 1 (satu) hari kerja tanpa surat keterangan dari dokter maka akan menjadi cuti tanpa tanggungan dan hari tidak masuk kerja tersebut menjadi salah satu penilaian dalam evaluasi tahunan.',
        ], '1)', 1)],
        'PIHAK KEDUA tidak masuk kerja karena sakit akibat kecelakaan kerja harus dinyatakan dengan berita acara mengenai kejadian sakitnya dari pejabat yang berwenang dan disertai dengan surat keterangan dokter pemeriksa.',
    ]) !!}

    <table class="ph"><tr><td class="pt"><div>Pasal 11</div><div>JAMINAN KESEHATAN</div></td><td class="pb"></td></tr></table>
<div class="p">PIHAK PERTAMA akan mengikutsertakan PIHAK KEDUA dalam program BPJS Kesehatan sesuai dengan kebijakan perusahaan yang mengacu pada Undang-Undang Nomor 6 Tahun 2023 tentang Penetapan Peraturan Pemerintah Pengganti Undang-Undang Nomor 2 Tahun 2022 tentang Cipta Kerja menjadi Undang-Undang</div>

    <table class="ph"><tr><td class="pt"><div>Pasal 12</div><div>PEMBATALAN PERJANJIAN KERJA DAN PENGUNDURAN DIRI</div></td><td class="pb"></td></tr></table>
{!! $list([
        ['PIHAK PERTAMA sewaktu-waktu dapat membatalkan Perjanjian Kerja Waktu Tidak Tertentu apabila ternyata PIHAK KEDUA melakukan Pelanggaran Bersifat Mendesak yang tercantum dalam Peraturan Perusahaan. Yang termasuk dalam Pelanggaran Bersifat Mendesak, yaitu:', $list([
            'melakukan penipuan, pencurian, dan/atau penggelapan barang dan/atau uang milik perusahaan;',
            'Memberikan keterangan palsu atau yang dipalsukan sehingga merugikan perusahaan;',
            'Mabuk, minum minuman keras yang memabukkan, memakai dan/atau mengedarkan narkotika, psikotropika, dan zat adiktif lainnya di lingkungan kerja;',
            'Merokok di area yang telah ditetapkan sebagai Kawasan Tanpa Rokok (KTR) menurut Peraturan Daerah (PERDA) Kota Banjarbaru Nomor 12 Tahun 2017 tentang Kawasan Tanpa Rokok dalam hal ini KTR ialah Fasilitas Pelayanan Kesehatan.',
            'Melakukan perbuatan asusila atau perjudian di lingkungan kerja;',
            'Menyerang, menganiaya, mengancam, atau mengintimidasi teman sekerja atau perusahaan di lingkungan kerja.',
            'Membujuk teman sekerja atau perusahaan melakukan perbuatan bertentangan dengan peraturan perundang-undangan;',
            'Dengan ceroboh atau sengaja merusak atau membiarkan dalam keadaan bahaya barang milik rumah sakit yang menimbulkan kerugian bagi perusahaan;',
            'Dengan ceroboh atau sengaja membiarkan teman sekerja atau rumah sakit dalam keadaan bahaya di tempat',
            'Membongkar atau membocorkan rahasia perusahaan dan/atau rahasia jabatan, kecuali untuk kepentingan negara;',
            'Melakukan kegiatan lainnya di lingkungan perusahaan yang diancam dengan pidana penjara 5 (lima) tahun atau lebih.',
        ], '1)', 1)],
        'PIHAK PERTAMA berhak untuk membatalkan Perjanjian Kerja Waktu Tidak Tertentu ini apabila diketahui PIHAK KEDUA tidak berhasil dalam menjalani Masa Percobaan pada Pasal 1 huruf (e).',
        'PIHAK KEDUA berhak untuk membatalkan Perjanjian Kerja Waktu Tidak Tertentu dengan mengajukan Surat Pengunduran Diri secara tertulis selambat-lambatnya 30 (tiga puluh) hari terhitung sejak diajukan oleh PIHAK KEDUA kepada PIHAK PERTAMA.',
        'Apabila PIHAK KEDUA alpha/mangkir dalam waktu sedikitnya 5 (lima) hari kerja berturut-turut tanpa keterangan tertulis dengan bukti yang sah dan telah dipanggil 2 (dua) kali oleh perusahaan, maka PIHAK KEDUA dianggap mengundurkan diri dari jabatan dan/atau posisinya dan PIHAK PERTAMA berhak untuk tidak mengeluarkan Surat Keterangan Kerja.',
        'Apabila PIHAK KEDUA selama melaksanakan tugas dan pekerjaannya pada PIHAK PERTAMA mendaftar sebagai Pegawai Negeri Sipil, Pegawai Badan Usaha Milik Negara, Pegawai Pemerintah dengan Perjanjian Kerja, dan/atau Pegawai Perusahaan Swasta Lainnya maka PIHAK KEDUA wajib secara tertulis mengajukan Pengunduran Diri dan tetap bekerja selama 30 (tiga puluh) hari sejak Surat Pengunduran Diri diajukan kepada PIHAK PERTAMA.',
        'Apabila PIHAK KEDUA tidak melanggar ketentuan pada Pasal 12 huruf (a) dan (d) dan/atau melanggar ketentuan Peraturan Perundang-undangan yang berlaku tentang syarat dan ketentuan sebagai penerima hak uang pesangon, uang pengganti hak dan uang pisah maka PIHAK PERTAMA akan membayarkan uang pesangon, uang pengganti hak, dan uang pisah sesuai dengan ketentuan Peraturan Perundang-undangan yang berlaku.',
        'PIHAK KEDUA wajib mengembalikan dan menyerahkan kepada PIHAK PERTAMA semua sarana dan prasarana yang telah diperoleh dan diterima dari PIHAK PERTAMA dalam rangka pelaksanaan tugas dan fungsinya berdasarkan Perjanjian ini.',
        'Para Pihak sepakat dan setuju bahwa jaminan tersebut dalam pasal 3 huruf h bagian ”Pihak Kedua berkewajiban” baru dapat diserahkan oleh PIHAK PERTAMA, dan baru dapat diterima atau diperoleh oleh PIHAK KEDUA, setelah seluruh kewajiban PIHAK KEDUA dalam Perjanjian ini telah diselesaikan dengan baik.',
    ]) !!}

    <table class="ph"><tr><td class="pt"><div>Pasal 13</div><div>PENGGANTIAN</div></td><td class="pb"></td></tr></table>
{!! $list([
        'PIHAK KEDUA bersedia mengganti kerugian atas kerusakan dan/atau kehilangan seragam atau ID Card atas kelalaian atau kesengajaan PIHAK KEDUA kepada PIHAK PERTAMA sebesar Rp150.000 (seratus lima puluh ribu rupiah) per seragam dan Rp100.000 (seratus ribu rupiah) per ID Card.',
        'PIHAK KEDUA bersedia mengganti segala kerugian finansial dan/atau non finansial yang diderita PIHAK PERTAMA sebagai akibat kelalaian atau kesengajaan yang ditimbulkan oleh PIHAK KEDUA, baik pada saat masih bekerja atau sudah tidak bekerja.',
        'PIHAK PERTAMA berhak untuk meminta ganti rugi terhadap PIHAK KEDUA apabila diketahui PIHAK KEDUA melanggar ketentuan pada Pasal 5 huruf (c), dan/atau Pasal 6 huruf (a), (j), dan/atau (m)',
    ]) !!}

    <table class="ph"><tr><td class="pt"><div>Pasal 14</div><div>LAIN-LAIN</div></td><td class="pb"></td></tr></table>
<div class="p">PIHAK PERTAMA berhak melakukan evaluasi atas tingkat kecakapan, keterampilan, kedisiplinan PIHAK KEDUA sebagaimana yang telah ditentukan dalam peraturan kekaryawanan yang berlaku di perusahaan dan penilaian khusus lainnya dari atasan langsung atau Direksi bila dianggap perlu. Sedangkan PIHAK KEDUA mengikatkan diri untuk menerima hasil evaluasi tersebut sebagai dasar pelaksanaan perjanjian ini selama tidak bertentangan dengan ketentuan hukum yang berlaku.</div>

    <table class="ph"><tr><td class="pt"><div>Pasal 15</div><div>PENUTUP</div></td><td class="pb"></td></tr></table>
{!! $list([
        'Hal-hal yang belum diatur dalam Perjanjian ini akan diatur kemudian dan menjadi bagian yang tidak terpisahkan dari Perjanjian ini serta mengikat kedua belah pihak.',
        'Perjanjian ini dengan segala akibat dan pelaksanaannya kedua belah pihak sepakat untuk menyelesaikan secara musyawarah mufakat dan apabila tidak mencapai mufakat maka kedua belah pihak memilih domisili hukum di Kantor Pengadilan Negeri Banjarbaru di Kalimantan Selatan. Domisili hukum tersebut berlaku pula terhadap (para) pengganti dan/atau (para) penerima hak dari kedua belah pihak.',
    ]) !!}

    <div class="p0">Demikianlah Perjanjian ini ditanda tangani oleh Para Pihak dalam keadaan sehat jasmani dan rohani tanpa adanya paksaan dari pihak manapun juga, serta dibuat dalam 2 (dua) rangkap dengan masing-masing rangkap mempunyai kekuatan hukum yang sama bagi Para Pihak.</div>
    <div class="p0" style="margin:12pt 0 14pt;">Banjarbaru, {!! $tgl($start ?? now(), '') !!}</div>

    <table class="sign">
        <tr><td><b>PIHAK PERTAMA</b></td><td><b>PIHAK KEDUA</b></td></tr>
        <tr><td class="sign-space"></td><td class="sign-space"></td></tr>
        <tr><td>Muhammad Fauzul Akbar, S. M., M.M</td><td>{{ $full_name }}</td></tr>
    </table>
</body>
</html>
