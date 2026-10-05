{{-- Tab riwayat: pelatihan sudah terlaksana (klaim JP) / belum terlaksana (biaya & ikatan dinas). --}}
<div style="display:inline-flex;gap:2px;background:var(--surface-alt);padding:3px;border-radius:8px;flex-wrap:wrap;margin-bottom:16px;">
    @foreach (['sudah' => ['Sudah Terlaksana', 'pelatihan.klaim'], 'belum' => ['Belum Terlaksana', 'pelatihan.pengajuan-saya']] as $key => [$text, $route])
        <a href="{{ route($route) }}" wire:navigate style="padding:5px 14px;font-size:12px;font-weight:600;border-radius:6px;text-decoration:none;background:{{ $active === $key ? 'var(--surface)' : 'transparent' }};color:{{ $active === $key ? 'var(--text-heading)' : 'var(--text-muted)' }};box-shadow:{{ $active === $key ? 'var(--shadow-card)' : 'none' }};">{{ $text }}</a>
    @endforeach
</div>
