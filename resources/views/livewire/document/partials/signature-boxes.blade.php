{{-- Kotak tanda tangan di bawah isi dokumen: yang sudah menandatangani langsung tampil QR verifikasi + nama + jabatan + waktu.
     Butuh $signable (SpReport), $boxes = [role => label] sesuai urutan tampil, $signatures (Collection keyBy signer_role). --}}
@php
    $qr = app(\App\Services\Sp\SpPdfService::class)->verificationBySignerRole($signable);
    // $cols (opsional) = jumlah kolom tetap; kotak terakhir sendirian di baris terakhir dipusatkan.
    $cols = $cols ?? null;
    $lastAlone = $cols && count($boxes) % $cols === 1;
@endphp
<div style="display:grid;grid-template-columns:{{ $cols ? "repeat($cols,minmax(0,1fr))" : 'repeat(auto-fit,minmax(190px,1fr))' }};gap:12px;">
    @foreach ($boxes as $role => $label)
        @php $sig = $signatures->get($role); $block = $qr[$role] ?? null; @endphp
        <div style="{{ $lastAlone && $loop->last ? 'grid-column:1 / -1;justify-self:center;width:calc(50% - 6px);' : '' }}border:1.5px {{ $sig ? 'solid #16a34a' : 'dashed var(--border)' }};border-radius:12px;padding:14px;text-align:center;background:{{ $sig ? 'var(--surface)' : 'var(--surface-alt)' }};">
            <p style="font-size:10.5px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:var(--text-muted);margin:0 0 10px;">{{ $label }}</p>
            @if ($sig && $block)
                <img src="{{ $block['qr'] }}" alt="QR Verifikasi {{ $label }}" style="width:96px;height:96px;margin:0 auto;display:block;">
                <p style="font-family:monospace;font-size:11px;letter-spacing:0.08em;color:var(--text-muted);margin:6px 0 0;">{{ $block['code'] }}</p>
                <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:10px 0 0;">{{ $sig->signer_name }}</p>
                <p style="font-size:11.5px;color:var(--text-muted);margin:2px 0 0;">{{ $sig->signer_position ?: '-' }}</p>
                <p style="font-size:11px;color:#15803d;margin:6px 0 0;"><i class="fa-solid fa-circle-check"></i> Ditandatangani {{ $sig->signed_at->translatedFormat('d M Y, H:i') }}</p>
                <a href="{{ route('public.verify', ['code' => $block['code']]) }}" target="_blank" style="font-size:11px;color:var(--brand);">Verifikasi</a>
            @else
                <div style="height:96px;display:flex;align-items:center;justify-content:center;color:var(--text-faint);font-size:24px;"><i class="fa-regular fa-clock"></i></div>
                <p style="font-size:12px;color:var(--text-muted);margin:10px 0 0;">Belum menandatangani</p>
            @endif
        </div>
    @endforeach
</div>
