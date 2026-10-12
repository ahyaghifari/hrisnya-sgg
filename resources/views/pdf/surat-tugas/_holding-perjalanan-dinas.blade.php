{{-- Halaman 2 Surat Perintah Tugas: formulir Surat Perintah Perjalanan Dinas (tabel). Di-include
     dari surat-tugas.blade.php; style (.spd-*) ada di sana. --}}
<div class="spd-page">
    <div class="title"><span class="title-text">SURAT PERINTAH PERJALANAN DINAS</span></div>
    <div class="nomor">No. {{ $surat->nomor ?: '-' }}</div>

    <table class="spd-form">
        <tr>
            <td class="no">1.</td>
            <td class="label">Pejabat yang berwenang memberi perintah</td>
            <td>{{ $surat->jabatan_ttd }} PT. Syifa Global Group</td>
        </tr>
        <tr>
            <td class="no">2.</td>
            <td class="label">Nama Karyawan yang diberi perintah</td>
            <td>
                @foreach ($surat->peserta as $peserta)
                    <div>- &nbsp;{{ $peserta->displayName() }}</div>
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="no">3.</td>
            <td class="label">Tanggal</td>
            <td>{!! collect($surat->periodeLinesSurat())->map(fn ($line) => e($line))->implode('<br>') !!}</td>
        </tr>
        <tr>
            <td class="no">4.</td>
            <td class="label">Transportasi yang digunakan</td>
            <td>{{ $surat->transportasi ?: '-' }}</td>
        </tr>
        <tr>
            <td class="no">5.</td>
            <td class="label">Tempat yang dituju</td>
            <td>{{ $surat->tempat }}</td>
        </tr>
        <tr>
            <td class="no">6.</td>
            <td class="label">Maksud dan Tujuan Penugasan</td>
            <td>Dalam rangka {!! nl2br(e($surat->tugas)) !!}</td>
        </tr>
        <tr>
            <td class="no spd-tall-7">7.</td>
            <td class="label">Nama, Tanda Tangan Petugas dan Stample Kantor yang dikunjungi</td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td class="no spd-tall-8">8.</td>
            <td class="label">Dan lain-lain</td>
            <td>{!! nl2br(e($surat->lain_lain)) !!}</td>
        </tr>
    </table>
</div>
