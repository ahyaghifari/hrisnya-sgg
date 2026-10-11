{{-- Blok tanda tangan template rumah-sakit (dipakai halaman 1 & 2), murni alur biasa:
     jabatan, QR TTE (kosong sebelum ditandatangani), nama, NIP. --}}
<div class="sign-wrap">
    <div class="sign-block">
        {{ $surat->jabatan_ttd }},
        @include('pdf.surat-tugas._ttd-stack')
        {{ $surat->nama_ttd }}<br>
        NIP {{ $surat->nip_ttd ?? $surat->ttdUser?->nip ?? '-' }}
    </div>
</div>
