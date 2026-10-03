{{-- Latar default SEMUA halaman panel /surat: gradasi biru + bentuk melayang (pola .deco-* HRIS) + topbar transparan.
     CSS biasa karena panel ini tanpa theme vite. Bentuk z-index -1 supaya di belakang konten tapi di atas gradasi. --}}
<style>
    body.fi-body { background: linear-gradient(160deg, #2f9fd8 0%, #4db3e6 55%, #5cc0ee 100%) fixed !important; }
    /* Topbar ikut scroll (tidak menempel di atas): bawaan Filament sticky top-0. z-index tetap supaya menu user di atas konten. */
    .fi-topbar-ctn { position: relative !important; }
    .fi-topbar, .fi-topbar > nav { background: transparent !important; box-shadow: none !important; --tw-ring-shadow: 0 0 #0000 !important; }
    .fi-topbar .fi-logo, .fi-topbar-item-label, .fi-topbar-item-icon { color: #fff !important; }
    /* Menu aktif: latar biru lebih gelap, teks & ikon putih. Hover ringan untuk yang tidak aktif. */
    .fi-topbar-item.fi-active .fi-topbar-item-btn { background: #1f6fa8 !important; }
    .fi-topbar-item:not(.fi-active) .fi-topbar-item-btn:hover { background: rgba(255,255,255,.15) !important; }
    .fi-header-heading { color: #fff !important; }

    .ps-deco { position: fixed; inset: 0; z-index: -1; pointer-events: none; overflow: hidden; }
    .ps-deco i { position: absolute; display: block; background: #fff; opacity: .1; }
    .ps-deco .a { top: -180px; right: -180px; width: 580px; height: 580px; border-radius: 50%; animation: ps-a 24s ease-in-out infinite; }
    .ps-deco .b { bottom: -120px; left: -120px; width: 420px; height: 420px; border-radius: 50%; opacity: .08; animation: ps-b 28s ease-in-out infinite; }
    .ps-deco .c { top: 42%; left: 62%; width: 220px; height: 220px; border-radius: 50%; background: none; border: 28px solid #fff; opacity: .09; animation: ps-c 20s ease-in-out infinite; }
    .ps-deco .d { top: 18%; left: 14%; width: 90px; height: 90px; border-radius: 14px; opacity: .09; animation: ps-d 17s ease-in-out infinite; }
    @keyframes ps-a { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(-50px, 60px) scale(1.06); } }
    @keyframes ps-b { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(55px, -45px) scale(1.05); } }
    @keyframes ps-c { 0%, 100% { transform: translate(0, 0) rotate(0deg); } 50% { transform: translate(-40px, -35px) rotate(20deg); } }
    @keyframes ps-d { 0%, 100% { transform: rotate(20deg) translate(0, 0); } 50% { transform: rotate(35deg) translate(25px, 30px); } }
    @media (prefers-reduced-motion: reduce) { .ps-deco i { animation: none; } }
</style>
<div class="ps-deco" aria-hidden="true"><i class="a"></i><i class="b"></i><i class="c"></i><i class="d"></i></div>
