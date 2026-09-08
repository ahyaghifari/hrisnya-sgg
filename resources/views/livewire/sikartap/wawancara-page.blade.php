<div>
    <div class="page-hero violet" style="margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Beranda</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Sikartap</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Wawancara Karyawan Tetap</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Pengajuan yang lulus tes siap dijadwalkan wawancara — bisa pilih banyak sekaligus</p>
        </div>
    </div>

    @include('livewire.sikartap.partials.tahap-list', ['tahap' => 'wawancara'])
</div>
