@props(['variant' => 'icon'])

{{-- Maskot pemandu: foto maskot di topbar, klik -> modal perkenalan. Modal di-teleport ke <body>
     supaya position:fixed tidak terjebak di dalam nav/app bar. --}}
<div x-data="{ open: false }" x-on:keydown.escape.window="open = false" style="display:flex;">
    <button type="button" x-on:click="open = true" aria-label="Buka pemandu maskot"
            style="width:{{ $variant === 'bar' ? '32px' : '36px' }};height:{{ $variant === 'bar' ? '32px' : '36px' }};padding:0;border-radius:{{ $variant === 'bar' ? '9px' : '10px' }};border:1.5px solid var(--border-soft);background:var(--surface);cursor:pointer;overflow:hidden;display:flex;">
        <img src="{{ asset('assets/mascot/halo.jpg') }}" alt="Maskot" style="width:100%;height:100%;object-fit:cover;">
    </button>

    {{-- Bounce: animasi CSS jalan ulang tiap overlay tampil (display none -> flex). --}}
    <style>
        @keyframes mascot-bounce-in {
            0% { opacity: 0; transform: scale(0.75) translateY(16px); }
            60% { opacity: 1; transform: scale(1.03) translateY(-2px); }
            100% { transform: scale(1) translateY(0); }
        }
        .mascot-modal-box { animation: mascot-bounce-in 0.4s ease-out both; }
        @media (prefers-reduced-motion: reduce) { .mascot-modal-box { animation: none; } }
    </style>

    <template x-teleport="body">
        <div class="modal-overlay" x-show="open" x-cloak x-transition.opacity x-on:click.self="open = false" style="align-items:center;">
            <div class="modal-box mascot-modal-box" role="dialog" aria-modal="true" aria-labelledby="mascot-guide-title" style="text-align:center;padding:28px 24px;">
                <img src="{{ asset('assets/mascot/halo.png') }}" alt="Maskot menyapa" style="width:140px;height:auto;margin:0 auto 12px;display:block;">
                <h2 id="mascot-guide-title" style="font-size:18px;font-weight:800;color:var(--text-heading);margin:0 0 8px;">Halo, salam kenal!</h2>
                <p style="font-size:13.5px;line-height:1.6;color:var(--text-muted);margin:0 0 20px;">
                    Semua informasi seputar kepegawaian akan aku bantu sampaikan ke kalian di sini, mulai dari panduan, aturan, sampai hal-hal yang perlu kalian tahu sebagai karyawan.
                </p>
                <button type="button" class="btn-primary" x-on:click="open = false">Mengerti</button>
            </div>
        </div>
    </template>
</div>
