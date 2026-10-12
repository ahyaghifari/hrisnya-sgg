{{-- Ruang tanda tangan, murni alur biasa (tanpa posisi absolut): kosong sebelum ditandatangani
     (untuk tanda tangan basah/menyusul), sesudahnya QR TTE (logo perusahaan sudah menyatu di
     tengah gambar QR). Butuh $verification dari SuratTugasPdfService; style .ttd-slot/.verify-qr
     di view induk. Tinggi slot tetap supaya posisi nama tidak bergeser antara draft dan terbit. --}}
<div class="ttd-slot">
    @if ($verification)
        <img src="{{ $verification['qr'] }}" alt="QR Verifikasi" class="verify-qr">
    @endif
</div>
