{{-- Modal besar berisi hanya QR absensi (masuk/pulang). Latar putih supaya terbaca scanner di dark mode.
     Ukuran QR dibatasi tinggi layar (vh) supaya selalu muat tanpa scroll. --}}
@if ($this->attendanceQrSvg)
    <style>.att-qr svg { width: 100%; height: auto; display: block; }</style>
    <div class="modal-overlay" wire:click.self="closeAttendanceQr" style="align-items:center;">
        <div style="position:relative;background:#fff;border-radius:16px;padding:22px 24px 24px;width:min(94vw,760px);text-align:center;color:#111;">
            <button type="button" wire:click="closeAttendanceQr" aria-label="Tutup" style="position:absolute;top:10px;right:10px;width:36px;height:36px;border:0;border-radius:50%;background:#f1f5f9;color:#111;font-size:18px;cursor:pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <p style="font-size:20px;font-weight:800;margin:0 40px 2px;">QR Absen {{ ucfirst($qrType) }}</p>
            <p style="font-size:13px;margin:0 40px 14px;color:#555;">{{ $this->course->meetings->firstWhere('id', $qrMeetingId)?->title }}</p>
            <div class="att-qr" style="width:min(78vw,66vh,560px);margin:0 auto;">{!! $this->attendanceQrSvg !!}</div>
        </div>
    </div>
@endif
