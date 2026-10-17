<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* Ukuran mengikuti draft Word: A4, margin atas 3,65cm (ruang kop), lain 2,54cm, Arial 12pt. */
        @page { margin: 3.65cm 2.54cm 2.54cm 2.54cm; }
        body { font-family: 'Arial', sans-serif; font-size: 12pt; color: #000; margin: 0; line-height: 1.24; text-align: justify; /* Word: 1,08 x tinggi baris alami Arial (1,15) */ }
        .bg { position: fixed; top: -3.65cm; left: -2.54cm; width: 21cm; height: 2.81cm; }
        .n { font-weight: normal; }
        .center { text-align: center; }
        .justify { text-align: justify; }
        .p { margin: 0 0 8pt; }
        .p0 { margin: 0; }
        .box { position: absolute; right: 0; top: 0; width: 3.2cm; border: 1px solid #000; text-align: center; font-weight: bold; padding: 4pt 0; }
        h1.title { text-align: center; text-decoration: underline; font-size: 12pt; font-weight: bold; margin: 0; }
        .nomor { text-align: center; font-weight: bold; margin: 0 0 12pt; }
        .pasal.t { margin-top: 8pt; }
        .pasal { text-align: center; font-weight: bold; margin: 0; page-break-after: avoid; }
        table { border-collapse: collapse; }
        table.info td, table.li td { text-align: justify; }
        table.info { width: 100%; }
        table.info td { padding: 0; vertical-align: top; }
        table.info td.no { width: 0.62cm; }
        table.info td.label { width: 3.16cm; }
        table.info td.sep { width: 0.5cm; }
        ol { margin: 0; padding-left: 0.75cm; text-align: justify; }
        ol.dec { list-style-type: decimal; text-align: justify; }
        ol.alpha { list-style-type: lower-alpha; }
        li { margin-bottom: 8pt; }
        table.sign { width: 100%; page-break-inside: avoid; }
        table.sign td { width: 50%; vertical-align: top; text-align: center; padding: 0; }
        .sign-space { height: 62pt; }
    </style>
</head>
<body>
    @if ($background_path)
        <img class="bg" src="{{ $background_path }}">
    @endif

    @php
        $pt = 'PT. Syifa Medika Persada';
        $p1Name = 'Muhammad Fauzul Akbar, S.M., M.M';
        $p1Title = 'Direktur';
        $end = $start?->copy()->addMonths(3)->subDay();
        $dots = '...........................';
    @endphp

    <div style="position:relative;">
        <div class="box">RAHASIA</div>
        <h1 class="title">PERJANJIAN KERJA MITRA</h1>
        <div class="nomor">{{ $nomor }}</div>
    </div>

    <div class="justify p0" style="margin-bottom:4pt;">Yang bertanda-tangan di bawah ini:</div>

    <table class="info">
        <tr><td class="no">I.</td><td class="label">Nama</td><td class="sep">:</td><td>{{ $p1Name }}</td></tr>
        <tr><td class="no"></td><td class="label">Jabatan</td><td class="sep">:</td><td>{{ $p1Title }}</td></tr>
    </table>
    <div class="justify p0" style="margin-bottom:12pt;padding-left:0.62cm;">Dalam hal ini bertindak untuk dan atas nama <b>{{ $pt }}</b> selanjutnya disebut “<b>PIHAK PERTAMA</b>”.</div>

    <table class="info">
        <tr><td class="no">II.</td><td class="label">Nama</td><td class="sep">:</td><td>{{ $full_name }}</td></tr>
        <tr><td class="no"></td><td class="label">Tanggal Lahir</td><td class="sep">:</td><td>{{ $birth_date }}</td></tr>
        <tr><td class="no"></td><td class="label">Jenis Kelamin</td><td class="sep">:</td><td>{{ $dots }}</td></tr>
        <tr><td class="no"></td><td class="label">No Telepon</td><td class="sep">:</td><td>{{ $dots }}</td></tr>
        <tr><td class="no"></td><td class="label">Alamat</td><td class="sep">:</td><td class="justify">{{ $address }}</td></tr>
        <tr><td class="no"></td><td class="label">Nomor KTP</td><td class="sep">:</td><td>{{ $nik }}</td></tr>
    </table>
    <div class="justify p0" style="margin-bottom:12pt;padding-left:0.62cm;">Selanjutnya disebut “<b>PIHAK KEDUA</b>”.</div>

    <div class="justify" style="margin-bottom:8pt;">Para Pihak sepakat dan setuju menerangkan terlebih dahulu hal-hal sebagai berikut:</div>
    <ol class="dec">
        <li><span class="n">PIHAK PERTAMA adalah {{ $pt }} suatu perseroan terbatas yang bergerak dalam bidang usaha
            penyelenggaraan dan pengelolaan Rumah Sakit Swasta yang memberikan pelayanan kesehatan meliputi pelayanan rawat jalan, rawat inap, gawat darurat, pelayanan medis spesialistik dan subspesialistik, pelayanan penunjang medis, pelayanan keperawatan dan kebidanan, serta pelayanan administrasi dan manajemen rumah sakit,
            yang dalam Perjanjian ini bertindak sebagai pemberi kerja.</span></li>
        <li><span class="n">PIHAK KEDUA adalah tenaga yang memenuhi persyaratan akademis sesuai dengan standar yang berlaku untuk melakukan pelayanan sesuai dengan bidang keahliannya, yang dalam Perjanjian ini bertindak sebagai pekerja.</span></li>
        <li><span class="n">PIHAK PERTAMA setuju menunjuk PIHAK KEDUA sebagai mitra dalam melaksanakan pelayanan rumah sakit PIHAK PERTAMA tersebut di atas, dengan syarat-syarat dan ketentuan sebagai berikut:</span></li>
    </ol>

    <div class="pasal t">PASAL 1</div>
    <div class="pasal" style="margin-bottom:16pt;">PEMBERIAN TUGAS</div>
    <ol class="alpha">
        <li><span class="n">PIHAK PERTAMA memberi penugasan kepada PIHAK KEDUA dan PIHAK KEDUA menerima penugasan dari PIHAK PERTAMA untuk melaksanakan pekerjaan sebagai:</span>
            <table class="info" style="font-weight:normal;margin-top:2pt;">
                <tr><td class="label" style="width:3.4cm;">Jabatan</td><td class="sep">:</td><td><b>{{ $jabatan }}</b></td></tr>
                <tr><td class="label" style="width:3.4cm;">Unit Kerja</td><td class="sep">:</td><td><b>{{ $unit ?: '-' }}</b></td></tr>
                <tr><td class="label" style="width:3.4cm;">Unit Penempatan</td><td class="sep">:</td><td><b>RSU Syifa Medika Banjarbaru</b></td></tr>
            </table>
        </li>
        <li><span class="n">Dalam kondisi tertentu dan atas kesepakatan kedua belah pihak, maka PIHAK PERTAMA dapat memberi penugasan lain selain yang tersebut dalam Pasal 1 butir a, tanpa mengurangi gaji dan fasilitas yang sudah diterima PIHAK KEDUA.</span></li>
    </ol>

    <div class="pasal t">PASAL 2</div>
    <div class="pasal" style="margin-bottom:16pt;">KEWAJIBAN</div>
    <div class="p">PIHAK KEDUA berkewajiban:</div>
    <ol class="alpha">
        <li><span class="n">Melaksanakan segala tugas dan kewajiban yang diberikan PIHAK PERTAMA sesuai dengan uraian tugas dengan penuh rasa tanggung jawab, jujur, penuh integritas, dan disiplin tinggi.</span></li>
        <li><span class="n">Mematuhi segala Peraturan Perusahaan, tata tertib yang berlaku, dan segala petunjuk pelaksanaan pekerjaan dari PIHAK PERTAMA.</span></li>
        <li><span class="n">PIHAK KEDUA wajib mengenakan pakaian yang pantas dan sopan, dan menggunakan Alat Pelindung Diri yang ditentukan PIHAK PERTAMA pada saat melaksanakan tugas dan pekerjaannya.</span></li>
        <li><span class="n">PIHAK KEDUA wajib hadir di rumah sakit PIHAK PERTAMA pada setiap hari pada jam operasional PIHAK PERTAMA sesuai dengan shift yang ditentukan.</span></li>
        <li><span class="n">Untuk menjamin pelaksanaan kewajiban PIHAK KEDUA kepada PIHAK PERTAMA berdasarkan Perjanjian ini, maka PIHAK KEDUA wajib menandatangani Surat Pernyataan Karyawan, Surat Pernyataan Orang Tua/Wali dan Pakta Integritas yang kemudian diserahkan kepada PIHAK PERTAMA.</span></li>
    </ol>

    <div class="pasal t">PASAL 3</div>
    <div class="pasal" style="margin-bottom:16pt;">PERJANJIAN</div>
    <div class="p">Selama masa Perjanjian Kerja, PIHAK KEDUA bersedia untuk:</div>
    <ol class="alpha">
        <li><span class="n">Tidak melakukan hal-hal yang menyimpang atau melanggar hukum, termasuk tapi tidak terbatas pada pencurian, manipulasi, penipuan, pemanfaatan, praktek perjudian, penggunaan hak milik perusahaan tanpa hak atau ketidakjujuran yang akan merugikan perusahaan.</span></li>
            <li><span class="n">Tidak mendokumentasikan pasien dalam bentuk apapun (foto, video, dan sejenisnya) tanpa seizin pasien dan atau keluarga pasien bersangkutan.</span></li>
        <li><span class="n">Tidak meminta atau menerima pemberian berupa barang atau uang dari pihak ketiga yang berkaitan atau akan mempengaruhi pelaksanaan tugas.</span></li>
        <li><span class="n">Tidak melakukan fitnah, ancaman, atau kekerasan pada sesama teman kerja atau atasan.</span></li>
        <li><span class="n">Tidak menggunakan dan atau membawa obat terlarang, narkotika atau minuman keras baik di luar maupun di lingkungan kerja atau pada jam kerja.</span></li>
        <li><span class="n">Tidak merokok dan atau tidak berbau rokok baik di lingkungan kerja atau pada jam kerja.</span></li>
        <li><span class="n">Tidak melakukan kelalaian atau kecerobohan kerja secara sengaja atau tidak sengaja sehingga mengakibatkan bahaya bagi sesama teman sekerja, atau kerugian bagi perusahaan.</span></li>
        <li><span class="n">Tidak melawan atau menolak instruksi atasan atau PIHAK PERTAMA yang wajar dan masih berkaitan dengan bidang tugasnya.</span></li>
        <li><span class="n">Tidak dalam kondisi hamil selama masa kontrak (khusus karyawan wanita).</span></li>
        <li><span class="n">Tidak melanggar peraturan perusahaan, tata tertib kerja, dan prosedur kerja.</span></li>
        <li><span class="n">Tidak melakukan pekerjaan rangkap di luar penugasan PIHAK PERTAMA.</span></li>
    </ol>

    <div class="pasal t">PASAL 4</div>
    <div class="pasal" style="margin-bottom:16pt;">MASA BERLAKU PERJANJIAN KERJA</div>
    <div class="p">Perjanjian Kerja ini berlaku untuk jangka waktu selama <b>3 (Tiga) bulan</b> terhitung dari tanggal {{ $start?->translatedFormat('d F Y') ?? '…………….' }} dan akan berakhir pada tanggal {{ $end?->translatedFormat('d F Y') ?? '...........................' }}.</div>

    <div class="pasal t">PASAL 5</div>
    <div class="pasal" style="margin-bottom:16pt;">IMBALAN JASA</div>
    <ol class="alpha">
        <li><span class="n">Sebagai imbalan atas pelaksanaan tugas dan pekerjaan PIHAK KEDUA, maka PIHAK PERTAMA telah menyetujui dan bersedia memberikan upah kepada PIHAK KEDUA yang sudah ditentukan besarannya oleh PIHAK PERTAMA, dan disetujui oleh PIHAK KEDUA.</span></li>
        <li><span class="n">Imbalan jasa per bulan akan dibayarkan setiap tanggal 30 (tiga puluh) setiap bulannya atau paling lambat 3 hari setelah tanggal 30 (tiga puluh).</span></li>
        <li><span class="n">Imbalan jasa tersebut di atas akan dibayarkan oleh PIHAK PERTAMA kepada PIHAK KEDUA dengan cara transfer ke rekening Bank Syariah Indonesia yang dimiliki PIHAK KEDUA.</span></li>
    </ol>

    <div class="pasal t">PASAL 6</div>
    <div class="pasal" style="margin-bottom:16pt;">PEMBATALAN PERJANJIAN KERJA</div>
    <ol class="alpha">
        <li><span class="n">PIHAK PERTAMA sewaktu-waktu dapat membatalkan Perjanjian Kerja apabila ternyata PIHAK KEDUA tidak memenuhi standar kualifikasi jabatan atau tidak dapat mentaati segala sesuatu yang telah disepakati bersama dalam Perjanjian ini, melanggar kewajiban seperti tercantum dalam Pasal 3 dan atau Pasal 4, dan untuk ini PIHAK KEDUA tidak dapat menuntut pembayaran apapun kepada PIHAK PERTAMA kecuali gaji dari pekerjaan yang telah dikerjakan.</span></li>
        <li><span class="n">Bila PIHAK KEDUA membatalkan Perjanjian Kerja sebelum berakhirnya jangka waktu yang ditetapkan dalam Perjanjian ini, maka PIHAK KEDUA harus memberitahukan PIHAK PERTAMA 1 (satu) bulan sebelumnya secara tertulis dan apabila PIHAK KEDUA masih terikat dalam Perjanjian Kerja ini maka PIHAK KEDUA diwajibkan membayar ganti rugi kepada PIHAK PERTAMA sebesar 3 (tiga) kali upah (gaji pokok terakhir yang diterima sebelum mengajukan pembatalan perjanjian kerja) PIHAK KEDUA. Apabila pemberitahuan pembatalan Perjanjian Kerja oleh PIHAK KEDUA kurang dari 1 (satu) bulan secara tertulis maka PIHAK KEDUA diwajibkan menyelesaikan masa kerjanya sampai dengan Surat Balasan dari Kepegawaian dikeluarkan.</span></li>
        <li><span class="n">Bila PIHAK KEDUA alpha/mangkir dalam waktu sedikitnya 5 (lima) hari kerja berturut-turut tanpa keterangan tertulis dengan bukti yang sah dan telah dipanggil 2 (dua) kali oleh Perusahaan, maka PIHAK KEDUA dikualifikasikan mengundurkan diri, namun tetap berkewajiban membayar ganti rugi kepada pihak lainnya sebesar 3 (tiga) kali upah PIHAK KEDUA.</span></li>
        <li><span class="n">PIHAK KEDUA wajib mengembalikan dan menyerahkan kepada PIHAK PERTAMA semua sarana dan prasarana yang telah diperoleh dan diterima dari PIHAK PERTAMA dalam rangka pelaksanaan tugas dan fungsinya berdasarkan Perjanjian ini.</span></li>
        <li><span class="n">PIHAK KEDUA bersedia mengganti kerugian atas kerusakan dan/atau hilangnya ID Card atas kelalaian atau kesengajaan PIHAK KEDUA kepada PIHAK PERTAMA sebesar Rp100.000 (seratus ribu rupiah).</span></li>
        <li><span class="n">PIHAK KEDUA bersedia mengganti segala kerugian finansial dan/atau non finansial yang diderita PIHAK PERTAMA sebagai akibat kelalaian atau kesengajaan yang ditimbulkan oleh PIHAK KEDUA, baik pada saat masih bekerja atau sudah berakhir masa Perjanjian kerjanya.</span></li>
    </ol>

    <div class="pasal t">PASAL 7</div>
    <div class="pasal" style="margin-bottom:16pt;">LAIN-LAIN</div>
    <div class="justify p">PIHAK PERTAMA berhak melakukan evaluasi atas tingkat kecakapan, keterampilan, kedisiplinan PIHAK KEDUA sebagaimana yang telah ditentukan dalam peraturan perusahaan {{ $pt }} pada waktu akhir perjanjian ini dan penilaian khusus lainnya dari atasan langsung atau Direksi bila dianggap perlu. Sedangkan PIHAK KEDUA mengikatkan diri untuk menerima hasil evaluasi tersebut sebagai dasar pelaksanaan perjanjian ini selama tidak bertentangan dengan ketentuan hukum yang berlaku.</div>

    <div class="pasal t">PASAL 8</div>
    <div class="pasal" style="margin-bottom:16pt;">KETENTUAN PENUTUP</div>
    <ol class="alpha" style="text-align:justify;">
        <li><span class="n">Hal-hal yang belum diatur dalam Perjanjian ini akan diatur kemudian dan menjadi bagian yang tidak terpisahkan dari Perjanjian ini serta mengikat kedua belah pihak.</span></li>
        <li><span class="n">Perjanjian ini dengan segala akibat dan pelaksanaannya kedua belah pihak sepakat untuk menyelesaikan secara musyawarah mufakat dan apabila tidak mencapai mufakat maka kedua belah pihak memilih domisili hukum di Kantor Pengadilan Negeri Banjarbaru di Kalimantan Selatan. Domisili hukum tersebut berlaku pula terhadap (para) pengganti dan/ atau (para) penerima hak dari kedua belah pihak.</span></li>
    </ol>

    <div class="justify p0">Demikianlah Perjanjian ini ditanda-tangani oleh Para Pihak dalam keadaan sehat jasmani dan rohani tanpa adanya paksaan dari pihak manapun juga, serta dibuat dalam 2 (dua) rangkap dengan masing-masing rangkap mempunyai kekuatan hukum yang sama bagi Para Pihak.</div>
    <div class="p0" style="margin:12pt 0 14pt;">Banjarbaru, {{ ($start ?? now())->translatedFormat('d F Y') }}</div>

    <table class="sign">
        <tr><td>PIHAK PERTAMA</td><td>PIHAK KEDUA</td></tr>
        <tr><td class="sign-space"></td><td class="sign-space"></td></tr>
        <tr><td><b>{{ $p1Name }}</b></td><td><b>{{ $full_name }}</b></td></tr>
    </table>
    
        <table class="sign" style="margin-top:8pt;">
            <tr><td colspan="2">Mengetahui,</td></tr>
            <tr><td colspan="2" class="sign-space"></td></tr>
            <tr><td colspan="2">dr. Puga Sharaz Wangi, M.A.R.S<br>Direktur RSU Syifa Medika Banjarbaru</td></tr>
        </table>
</body>
</html>
