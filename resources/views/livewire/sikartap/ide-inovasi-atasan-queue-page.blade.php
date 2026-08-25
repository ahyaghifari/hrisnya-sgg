<div>
    <div class="page-hero violet" style="margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Beranda</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Sikartap</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">💡 Penilaian Ide Inovasi</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Ide inovasi dari unit kerja Anda yang menunggu penilaian</p>
        </div>
    </div>

    @if ($this->queue->isEmpty())
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);">Tidak ada ide inovasi yang menunggu penilaian Anda saat ini.</p>
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:12px;">
            @foreach ($this->queue as $sikartap)
                <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div>
                        <p style="font-size:13px;font-weight:700;color:var(--text-heading);margin:0;">{{ $sikartap->user->name }}</p>
                        <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $sikartap->user->nip }} &middot; {{ $sikartap->user->company?->name }}</p>
                    </div>
                    <a href="{{ route('sikartap.ide-inovasi.atasan', $sikartap) }}" wire:navigate class="btn-primary" style="font-size:12px;padding:8px 16px;">
                        <i class="fa-solid fa-star"></i> Nilai
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>
