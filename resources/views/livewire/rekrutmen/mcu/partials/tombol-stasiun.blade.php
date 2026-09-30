{{--
    Tombol satu stasiun MCU di antrian. Perawat, lab & radiologi membuka modal di halaman ini
    (isiannya cuma unggah satu berkas); dokter pindah ke halamannya sendiri karena dia perlu membaca
    berkas ketiga stasiun itu dulu sebelum memutuskan fit/tidak fit.
--}}
@if ($st['modal'])
    <button type="button" wire:click="openStation('{{ $st['inviteeId'] }}', '{{ $st['key'] }}')"
        class="btn-ghost" style="padding:8px 14px;font-size:13px;" title="{{ $st['label'] }}">
        <i class="fa-solid {{ $st['icon'] }}"></i> {{ $st['label'] }}
        @if ($st['done']) <i class="fa-solid fa-check" style="color:var(--brand-success);"></i> @endif
    </button>
@else
    <a href="{{ $st['url'] }}" wire:navigate class="btn-ghost" style="padding:8px 14px;font-size:13px;" title="{{ $st['label'] }}">
        <i class="fa-solid {{ $st['icon'] }}"></i> {{ $st['label'] }}
        @if ($st['done']) <i class="fa-solid fa-check" style="color:var(--brand-success);"></i> @endif
    </a>
@endif
