<div>
<x-page-header title="Beranda" size="lg" />

{{-- Wrapper kartu persetujuan/tanda tangan: kartunya render display:none kalau kosong, tapi margin-bottom 20px wrapper tetap
makan tempat -> jarak ekstra di atas welcome card. Margin cuma berlaku kalau ada anak yang tampil. --}}
<style>
    .dash-approvals { margin-bottom: 20px; }
    .dash-approvals:not(:has(> [wire\:id]:not([style*="display:none"]):not([style*="display: none"]))) { margin-bottom: 0; }
</style>
<div class="dash-approvals">
    <livewire:pending-approvals-card />
    <livewire:presensi.shift-swap-approvals-card />
    <livewire:document.pending-signatures-card />
</div>

{{-- Banner pengumuman: ikon X menyembunyikan per pengumuman selama 24 jam (localStorage per perangkat), lalu muncul lagi
selama pengumuman masih berlaku (banner_until); pengumuman baru selalu muncul. --}}
@if ($this->announcementBanners->isNotEmpty())
<style>
    .ann-banner { --ann-accent: var(--info-soft-text); display: flex; align-items: center; gap: 14px; margin-bottom: 16px; padding: 14px 16px; border-radius: 16px; color: var(--text-heading); position: relative; overflow: hidden; background: var(--surface); }
    .ann-banner.ann-success { --ann-accent: var(--brand-success); }
    .ann-banner.ann-warning { --ann-accent: var(--warn-soft-text); }
    .ann-banner > :not(.ann-bg) { position: relative; }
    /* Latar soft pakai token tema (otomatis light/dark). 3 lapis ditumpuk dan di-crossfade lewat opacity karena warna latar ganti per slide. */
    .ann-bg { position: absolute; inset: 0; border-radius: inherit; opacity: 0; transition: opacity .6s ease; pointer-events: none; }
    .ann-bg.on { opacity: 1; }
    .ann-bg-info { background: var(--info-soft-bg); box-shadow: inset 0 0 0 1px var(--info-soft-border); }
    .ann-bg-success { background: color-mix(in srgb, var(--brand-success) 12%, transparent); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand-success) 35%, transparent); }
    .ann-bg-warning { background: var(--warn-soft-bg); box-shadow: inset 0 0 0 1px var(--warn-soft-border); }
    /* Pola garis diagonal tipis + dua lingkaran dekor + watermark megafon, warna ikut tone (currentColor), memudar ke kiri supaya teks tetap terbaca. */
    .ann-bg-info { color: var(--info-soft-text); }
    .ann-bg-success { color: var(--brand-success); }
    .ann-bg-warning { color: var(--warn-soft-text); }
    .ann-bg::before { content: ''; position: absolute; inset: 0; opacity: .16; background-image: repeating-linear-gradient(135deg, currentColor 0 1px, transparent 1px 12px);
        -webkit-mask-image: linear-gradient(to left, #000 0%, transparent 70%); mask-image: linear-gradient(to left, #000 0%, transparent 70%); }
    .ann-bg::after { content: ''; position: absolute; right: -50px; top: -70px; width: 190px; height: 190px; border-radius: 50%; border: 24px solid currentColor; opacity: .08;
        box-shadow: -150px 120px 0 -60px currentColor; }
    .ann-mark { position: absolute !important; right: 64px; bottom: -14px; font-size: 76px; line-height: 1; color: var(--ann-accent); opacity: .09; transform: rotate(-12deg); pointer-events: none; transition: color .6s ease; }
    .ann-icon img { width: 100%; height: 100%; object-fit: contain; }
    /* Maskot langsung tanpa kotak; margin negatif vertikal supaya banner tidak ikut membengkak. */
    .ann-icon { flex-shrink: 0; width: 84px; height: 84px; margin: -12px 0; display: flex; align-items: center; justify-content: center; }
    @media (max-width: 480px) { .ann-icon { width: 64px; height: 64px; margin: -6px 0; } }
    .ann-body { flex: 1; min-width: 0; display: grid; align-items: center; }
    .ann-link { grid-area: 1 / 1; min-width: 0; text-decoration: none; color: inherit; opacity: 0; transform: translateY(6px); pointer-events: none; transition: opacity .6s ease, transform .6s ease; }
    .ann-link.on { opacity: 1; transform: none; pointer-events: auto; }
    .ann-eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ann-accent); }
    .ann-title { margin: 2px 0 0; font-size: 15px; font-weight: 800; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ann-sum { margin: 2px 0 0; font-size: 12.5px; color: var(--text-body); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ann-side { flex-shrink: 0; align-self: stretch; display: flex; flex-direction: column; align-items: flex-end; justify-content: space-between; }
    .ann-x { background: none; border: 0; color: var(--text-faint); cursor: pointer; font-size: 14px; padding: 0; }
    .ann-x:hover { color: var(--text-heading); }
    .ann-dots { display: flex; gap: 5px; }
    .ann-dot { width: 6px; height: 6px; border-radius: 999px; border: 0; padding: 0; background: var(--text-faint); opacity: .45; cursor: pointer; transition: width .2s, opacity .2s, background .6s ease; }
    .ann-dot.on { width: 18px; background: var(--ann-accent); opacity: 1; }
</style>
{{-- Carousel otomatis (5 dtk, berhenti saat hover/disentuh) kalau lebih dari 1. X menyembunyikan per pengumuman 24 jam (localStorage per perangkat). --}}
<div class="ann-banner" :class="'ann-' + (items[i]?.tone || 'info')"
     x-data="{ all: @js($this->announcementBanners->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'tone' => $a->tone, 'summary' => \Illuminate\Support\Str::limit((string) $a->summary, 140), 'url' => route('pengumuman.show', $a)])->values()),
               items: [], i: 0, timer: null,
               init() {
                   let gone = [];
                   // Nilai tersimpan = waktu ditutup (ms). Disembunyikan 24 jam, lalu muncul lagi selama masih berlaku (banner_until, difilter server).
                   // Nilai lama '1' (versi sebelumnya) terbaca sebagai sangat lampau, jadi otomatis muncul lagi.
                   try { const now = Date.now(); gone = this.all.filter(a => { const t = Number(localStorage.getItem('ann-dismissed-' + a.id)); return t > 0 && now - t < 86400000; }).map(a => a.id); } catch (e) {}
                   this.items = this.all.filter(a => !gone.includes(a.id));
                   this.play();
               },
               play() { this.stop(); if (this.items.length > 1) this.timer = setInterval(() => this.i = (this.i + 1) % this.items.length, 5000); },
               stop() { clearInterval(this.timer); },
               dismiss() {
                   try { localStorage.setItem('ann-dismissed-' + this.items[this.i].id, String(Date.now())); } catch (e) {}
                   this.items.splice(this.i, 1); this.i = 0; this.play();
               } }"
     x-show="items.length" x-cloak x-on:mouseenter="stop()" x-on:mouseleave="play()" x-on:touchstart.passive="stop()">
    <template x-for="t in ['info', 'success', 'warning']"><div class="ann-bg" :class="'ann-bg-' + t + ((items[i]?.tone || 'info') === t ? ' on' : '')"></div></template>
    <i class="fa-solid fa-bullhorn ann-mark" aria-hidden="true"></i>
    <span class="ann-icon"><img src="{{ asset('assets/mascot/announcement.png') }}" alt="" aria-hidden="true"></span>
    <div class="ann-body">
        <template x-for="(a, n) in items" :key="a.id">
            <a class="ann-link" :class="{ on: n === i }" :href="a.url" wire:navigate :tabindex="n === i ? 0 : -1">
                <span class="ann-eyebrow">Pengumuman</span>
                <p class="ann-title" x-text="a.title"></p>
                <p class="ann-sum" x-show="a.summary" x-text="a.summary"></p>
            </a>
        </template>
    </div>
    <div class="ann-side">
        <button type="button" class="ann-x" aria-label="Tutup" x-on:click="dismiss()"><i class="fa-solid fa-xmark"></i></button>
        <div class="ann-dots" x-show="items.length > 1">
            <template x-for="(a, n) in items" :key="a.id">
                <button type="button" class="ann-dot" :class="{ on: n === i }" :aria-label="'Pengumuman ' + (n + 1)" x-on:click="i = n; play()"></button>
            </template>
        </div>
    </div>
</div>
@endif

@include('livewire.partials.home-cards')
</div>