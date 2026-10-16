{{-- Pilih metode absensi per pertemuan + tombol QR masuk/pulang. Dipakai admin LMS & pemateri.
     Butuh $meeting; method di-render ulang dari DB lewat setAttendanceMethod. --}}
@if ($showMethodSelect ?? true)
<select wire:change="setAttendanceMethod('{{ $meeting->id }}', $event.target.value)" class="form-input" style="padding:4px 8px;font-size:11px;width:auto;" aria-label="Metode absensi">
    <option value="manual" @selected($meeting->attendance_method !== 'qr')>Absensi Manual</option>
    <option value="qr" @selected($meeting->attendance_method === 'qr')>Scan QR</option>
</select>
@endif

@if ($meeting->attendance_method === 'qr')
    <button type="button" wire:click="showAttendanceQr('{{ $meeting->id }}', 'masuk')" class="btn-ghost" style="padding:5px 10px;font-size:11px;">
        <i class="fa-solid fa-qrcode"></i> QR Masuk
    </button>
    <button type="button" wire:click="showAttendanceQr('{{ $meeting->id }}', 'pulang')" class="btn-ghost" style="padding:5px 10px;font-size:11px;">
        <i class="fa-solid fa-qrcode"></i> QR Pulang
    </button>
@endif
