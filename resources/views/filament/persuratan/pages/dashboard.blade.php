<x-filament-panels::page>
    {{-- CSS biasa (bukan Tailwind): panel ini tidak punya theme vite, jadi utility class kustom tidak ter-compile. --}}
    <style>
        /* Latar/bentuk/topbar ada di filament/persuratan/background.blade.php (berlaku semua halaman panel). */

        .ps-wrap { position: relative; max-width: 76rem; margin: 0 auto; padding: 6vh 1rem 4rem;
            text-align: center; box-sizing: border-box; }
        .ps-head { display: flex; align-items: center; justify-content: center; gap: 1rem; }
        .ps-logo { width: 3.5rem; height: 3.5rem; border-radius: 9999px; background: rgba(255,255,255,.3);
            display: flex; align-items: center; justify-content: center; }
        .ps-logo svg { width: 1.75rem; height: 1.75rem; color: #fff; }
        .ps-title { margin: 0; font-size: 2.25rem; font-weight: 700; color: #fff; }
        .ps-sub { margin: .75rem auto 0; max-width: 36rem; font-size: .9375rem; line-height: 1.6; color: rgba(255,255,255,.9); }

        .ps-search { margin-top: 2rem; width: 100%; display: flex; align-items: center; background: rgba(255,255,255,.95);
            border-radius: 9999px; padding: .375rem; box-shadow: 0 10px 25px rgba(0,0,0,.12); box-sizing: border-box; }
        .ps-search svg { width: 1.25rem; height: 1.25rem; margin-left: .75rem; flex-shrink: 0; color: #6b7280; }
        .ps-search input { flex: 1; min-width: 0; border: 0; background: transparent; outline: none; box-shadow: none;
            padding: .5rem .75rem; font-size: .875rem; color: #1f2937; }
        .ps-search input:focus { box-shadow: none; outline: none; }
        .ps-btn { border: 0; cursor: pointer; border-radius: 9999px; background: #1f6fa8; color: #fff;
            padding: .5rem 1.5rem; font-size: .875rem; font-weight: 500; }
        /* Cari = fokus utama: lebih besar di desktop. */
        @media (min-width: 768px) {
            .ps-search { padding: .5rem; }
            .ps-search svg { width: 1.75rem; height: 1.75rem; margin-left: 1.25rem; }
            .ps-search input { padding: 1.1rem 1.25rem; font-size: 1.25rem; }
            .ps-search .ps-btn { padding: 1.1rem 2.5rem; font-size: 1.125rem; }
        }
        .ps-btn:hover { background: #185d8f; }

        .ps-card { margin-top: 1rem; width: 100%; background: rgba(255,255,255,.95); border-radius: .75rem; text-align: left;
            box-shadow: 0 10px 25px rgba(0,0,0,.12); box-sizing: border-box; }
        .ps-item { padding: .75rem 1.25rem; border-top: 1px solid #f3f4f6; }
        .ps-item:first-child { border-top: 0; }
        .ps-item b { display: block; font-size: .875rem; color: #111827; }
        .ps-item span { display: block; font-size: .875rem; color: #4b5563; }
        .ps-item small { display: block; font-size: .75rem; color: #9ca3af; }

        .ps-narrow { max-width: 50rem; margin: 0 auto; }
        /* Riwayat: daftar kartu berjarak & membulat, langsung di atas latar biru (bukan kartu putih besar). */
        .ps-riwayat { margin-top: 3.5rem; text-align: left; }
        .ps-riwayat-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
        .ps-riwayat-head h2 { margin: 0; font-size: 1.125rem; font-weight: 700; color: #fff; }
        .ps-filters { display: flex; flex-wrap: wrap; gap: .5rem; }
        /* Dropdown filter kustom (components/persuratan/filter-select). */
        .ps-fs { position: relative; }
        .ps-fs-btn { display: inline-flex; align-items: center; gap: .5rem; max-width: 17rem; height: 2.5rem; padding: 0 .875rem;
            border: 1px solid rgba(255,255,255,.55); border-radius: 9999px; background: rgba(255,255,255,.22); color: #fff;
            font-size: .8125rem; font-weight: 500; cursor: pointer; outline: none;
            -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); transition: background .15s, border-color .15s, box-shadow .15s; }
        .ps-fs-btn:hover { background: rgba(255,255,255,.34); }
        .ps-fs-btn:focus-visible { box-shadow: 0 0 0 3px rgba(255,255,255,.55); }
        .ps-fs-btn.on { background: #fff; border-color: #fff; color: #1f6fa8; }
        .ps-fs-ico, .ps-fs-chev { width: 1.125rem; height: 1.125rem; flex-shrink: 0; }
        .ps-fs-chev { opacity: .85; }
        .ps-fs-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ps-fs-clear { display: inline-flex; align-items: center; justify-content: center; width: 1.25rem; height: 1.25rem; margin-right: -.25rem;
            border-radius: 9999px; background: #e0f2fe; color: #1f6fa8; }
        .ps-fs-clear:hover { background: #bae6fd; }
        .ps-fs-clear svg { width: .875rem; height: .875rem; }
        .ps-fs-panel { position: absolute; z-index: 40; top: calc(100% + .5rem); left: 0; width: max(100%, 18rem); max-width: 22rem;
            background: #fff; border-radius: 1rem; box-shadow: 0 16px 40px rgba(15,60,100,.28); overflow: hidden; }
        .ps-fs-panel.right { left: auto; right: 0; }
        .ps-fs-panel { max-width: min(22rem, calc(100vw - 2rem)); }
        .ps-fs-search { display: flex; align-items: center; gap: .5rem; padding: .625rem .875rem; border-bottom: 1px solid #f1f5f9; }
        .ps-fs-search svg { width: 1rem; height: 1rem; color: #94a3b8; flex-shrink: 0; }
        .ps-fs-search input { flex: 1; min-width: 0; border: 0; outline: none; box-shadow: none; background: transparent;
            font-size: .8125rem; color: #1e293b; padding: .125rem 0; }
        .ps-fs-search input:focus { box-shadow: none; outline: none; }
        .ps-fs-opts { max-height: 17rem; overflow-y: auto; padding: .375rem; }
        .ps-fs-opt { display: flex; align-items: center; gap: .5rem; width: 100%; padding: .5rem .625rem; border: 0; border-radius: .625rem;
            background: none; text-align: left; font-size: .8125rem; color: #334155; cursor: pointer; }
        .ps-fs-opt:hover { background: #f1f5f9; }
        .ps-fs-opt.sel { background: #e0f2fe; color: #0c4a6e; font-weight: 600; }
        .ps-fs-dot { width: .625rem; height: .625rem; border-radius: 9999px; flex-shrink: 0; }
        .ps-riwayat-head { position: relative; z-index: 20; }
        .ps-term { font-weight: 500; opacity: .9; word-break: break-word; }
        .ps-clear-search { display: inline-flex; align-items: center; gap: .375rem; height: 2.25rem; padding: 0 .875rem; border-radius: 9999px;
            border: 1px solid rgba(255,255,255,.55); background: rgba(255,255,255,.22); color: #fff; font-size: .8125rem; font-weight: 500; cursor: pointer; }
        .ps-clear-search:hover { background: rgba(255,255,255,.34); }
        .ps-clear-search svg { width: 1rem; height: 1rem; }
        .ps-pager select { border: 1px solid #d1d5db; border-radius: 9999px; background: #fff; color: #374151;
            padding: .4rem 1.75rem .4rem .875rem; font-size: .8125rem; max-width: 16rem; }
        .ps-list { display: flex; flex-direction: column; gap: .75rem; }
        .ps-row { display: flex; align-items: center; gap: 1rem; padding: .875rem 1.125rem; background: rgba(255,255,255,.96);
            border-radius: 1rem; box-shadow: 0 4px 14px rgba(15,60,100,.1); transition: box-shadow .15s, transform .15s; }
        .ps-row:hover { box-shadow: 0 8px 22px rgba(15,60,100,.18); transform: translateY(-1px); }
        .ps-row.clickable { cursor: pointer; }
        .ps-row.sel { outline: 2px solid #1f6fa8; outline-offset: -2px; }
        .ps-row:focus-visible { outline: 3px solid rgba(255,255,255,.8); outline-offset: 2px; }

        /* Panel detail di sisi kanan. */
        .ps-drawer-bg { position: fixed; inset: 0; z-index: 60; background: rgba(15,40,70,.45); animation: ps-fade .18s ease-out; }
        .ps-drawer { position: fixed; top: 0; right: 0; bottom: 0; z-index: 61; width: min(28rem, 100vw); display: flex; flex-direction: column;
            background: #fff; box-shadow: -16px 0 40px rgba(15,60,100,.3); text-align: left; animation: ps-slide .22s ease-out; }
        @keyframes ps-fade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes ps-slide { from { transform: translateX(100%); } to { transform: translateX(0); } }
        .ps-drawer-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.25rem 1.25rem 1rem;
            background: #1f6fa8; color: #fff; }
        .ps-drawer-nomor { font-size: 1.0625rem; font-weight: 700; word-break: break-all; }
        .ps-drawer-badges { display: flex; flex-wrap: wrap; align-items: center; gap: .375rem; margin-top: .5rem; }
        .ps-drawer-badges .ps-jenis, .ps-drawer-badges .ps-badge { margin: 0; }
        .ps-drawer-badges .ps-badge { background: rgba(255,255,255,.2); color: #fff; }
        .ps-drawer-x { flex-shrink: 0; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; border: 0;
            border-radius: 9999px; background: rgba(255,255,255,.18); color: #fff; cursor: pointer; }
        .ps-drawer-x:hover { background: rgba(255,255,255,.3); }
        .ps-drawer-x svg { width: 1.125rem; height: 1.125rem; }
        .ps-drawer-body { flex: 1; overflow-y: auto; margin: 0; padding: .5rem 1.25rem; }
        .ps-drawer-body > div { padding: .75rem 0; border-bottom: 1px solid #f1f5f9; }
        .ps-drawer-body dt { font-size: .6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; }
        .ps-drawer-body dd { margin: .25rem 0 0; font-size: .9375rem; color: #1e293b; }
        .ps-drawer-body dd.pre { white-space: pre-wrap; }
        .ps-drawer-body dd small { color: #64748b; font-size: .8125rem; }
        .ps-drawer-foot { padding: 1rem 1.25rem; border-top: 1px solid #f1f5f9; }
        .ps-row-empty { justify-content: center; color: #6b7280; font-size: .875rem; padding: 1.5rem; }
        .ps-logo-co { flex-shrink: 0; width: 3.5rem; height: 3.5rem; border-radius: .875rem; display: flex; align-items: center; justify-content: center;
            padding: .25rem; box-sizing: border-box; color: #fff; font-size: .6875rem; font-weight: 700; letter-spacing: .02em;
            text-align: center; line-height: 1.1; word-break: break-all; }
        .ps-row-main { flex: 1; min-width: 0; }
        .ps-row-nomor { font-size: .875rem; font-weight: 700; color: #111827; }
        .ps-row-judul { font-size: .9375rem; color: #374151; margin-top: .125rem; overflow: hidden; text-overflow: ellipsis; display: -webkit-box;
            -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .ps-row-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .375rem; font-size: .8125rem; color: #374151; margin-top: .375rem; }
        .ps-unit { font-weight: 500; }
        .ps-ico { width: 1rem; height: 1rem; flex-shrink: 0; color: #6b7280; }
        .ps-jenis { display: inline-block; margin-left: .25rem; padding: .125rem .625rem; border-radius: 9999px;
            font-size: .6875rem; font-weight: 600; vertical-align: middle; }
        .ps-row-tgl { flex-shrink: 0; display: flex; align-items: center; gap: .375rem; font-size: .8125rem; color: #4b5563; white-space: nowrap; }
        @media (max-width: 640px) { .ps-row { flex-wrap: wrap; } .ps-row-tgl { width: 100%; padding-left: 4.5rem; margin-top: -.5rem; } }
        .ps-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-top: 1rem;
            padding: .625rem 1rem; background: rgba(255,255,255,.96); border-radius: 9999px; }
        .ps-pager-info { font-size: .8125rem; color: #6b7280; padding-left: .5rem; }
        .ps-pager-nav { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem; }
        .ps-pager-nav select { margin-right: .5rem; }
        .ps-pager-nav button { min-width: 2rem; height: 2rem; padding: 0 .5rem; border: 1px solid #e5e7eb; border-radius: 9999px;
            background: #fff; color: #374151; font-size: .8125rem; cursor: pointer; }
        .ps-pager-nav button:hover:not(:disabled) { background: #f3f4f6; }
        .ps-pager-nav button.on { background: #1f6fa8; border-color: #1f6fa8; color: #fff; }
        .ps-pager-nav button:disabled { opacity: .4; cursor: default; }
        .ps-pager-nav .gap { padding: 0 .25rem; color: #9ca3af; }
        .ps-badge { margin-left: .375rem; padding: .0625rem .4rem; border-radius: 9999px; background: #e0f2fe; color: #0369a1;
            font-size: .6875rem; font-style: normal; font-weight: 600; vertical-align: middle; }
        .ps-gen { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border: 0; cursor: pointer;
            background: #1f6fa8; transition: background .15s; }
        .ps-gen:hover { background: #185d8f; }
        .ps-gen .ico { width: 2.75rem; height: 2.75rem; border-radius: 9999px; background: rgba(255,255,255,.2); color: #fff; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; }
        .ps-gen .ico svg { width: 1.5rem; height: 1.5rem; }
        .ps-gen .txt { flex: 1; }
        .ps-gen .txt b { display: block; font-size: .875rem; color: #fff; }
        .ps-gen .txt small { display: block; font-size: .75rem; color: rgba(255,255,255,.8); }
        .ps-gen .chev { width: 1.25rem; height: 1.25rem; color: #fff; }

        .ps-hasil { padding: 1rem 1.25rem; }
        .ps-hasil small { font-size: .75rem; color: #6b7280; }
        .ps-hasil-row { display: flex; align-items: center; gap: .75rem; margin-top: .25rem; }
        .ps-hasil strong { font-size: 1.25rem; letter-spacing: .02em; color: #111827; word-break: break-all; }
        .ps-copy { flex-shrink: 0; width: 2.25rem; height: 2.25rem; display: flex; align-items: center; justify-content: center;
            border: 1px solid #e2e8f0; border-radius: .625rem; background: #fff; color: #475569; cursor: pointer;
            transition: background .15s, color .15s, border-color .15s; }
        .ps-copy:hover { background: #f1f5f9; color: #1f6fa8; border-color: #cbd5e1; }
        .ps-copy.done { background: #dcfce7; border-color: #86efac; color: #15803d; }
        .ps-copy svg { width: 1.125rem; height: 1.125rem; }
    </style>

    <div class="ps-hero">

        <div class="ps-wrap">
            <div class="ps-head">
                <div class="ps-logo"><x-filament::icon icon="heroicon-s-document-text" /></div>
                <h1 class="ps-title">HRIS Nomor Surat</h1>
            </div>
            <p class="ps-sub">Sistem penomoran dan arsip surat resmi perusahaan. Telusuri surat yang telah diterbitkan berdasarkan nomor, perihal, atau pembuatnya, atau terbitkan nomor surat baru sesuai format yang berlaku.</p>

            <div class="ps-narrow">
            <form wire:submit="cari" class="ps-search">
                <x-filament::icon icon="heroicon-m-magnifying-glass" />
                <input type="text" wire:model="q" placeholder="Cari nomor surat, perihal, atau nama pembuat…">
                <button type="submit" class="ps-btn">Cari</button>
            </form>

            <button type="button" wire:click="mountAction('generate')" class="ps-card ps-gen">
                <span class="ico"><x-filament::icon icon="heroicon-m-plus" /></span>
                <span class="txt">
                    <b>Buat Nomor Surat</b>
                    <small>Buat nomor surat baru sesuai format yang berlaku.</small>
                </span>
                <x-filament::icon icon="heroicon-m-chevron-right" class="chev" />
            </button>

            @if ($hasilNomor)
                <div class="ps-card ps-hasil" x-data="{ copied: false }">
                    <small>Nomor surat baru</small>
                    <div class="ps-hasil-row">
                        <strong>{{ $hasilNomor }}</strong>
                        <button type="button" class="ps-copy" x-bind:class="{ done: copied }"
                                x-bind:title="copied ? 'Tersalin' : 'Salin nomor surat'" aria-label="Salin nomor surat"
                                x-on:click="navigator.clipboard.writeText(@js($hasilNomor)); copied = true; setTimeout(() => copied = false, 1500)">
                            <x-filament::icon icon="heroicon-o-clipboard-document" x-show="! copied" />
                            <x-filament::icon icon="heroicon-m-check" x-show="copied" x-cloak />
                        </button>
                    </div>
                </div>
            @endif
            </div>

            @php
                    $riwayat = $this->riwayat();
                    $last = $riwayat->lastPage();
                    $cur = $riwayat->currentPage();
                    // Jendela halaman: 1 … (cur-1) cur (cur+1) … last
                    $pages = collect([1, $cur - 1, $cur, $cur + 1, $last])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
                    $warna = ['#1f6fa8', '#0f766e', '#7c3aed', '#c2410c', '#be185d', '#4d7c0f'];
                @endphp

                <section class="ps-riwayat">
                    <div class="ps-riwayat-head">
                        @if ($kataCari !== '')
                            <h2>Hasil pencarian <span class="ps-term">“{{ $kataCari }}”</span></h2>
                            <button type="button" class="ps-clear-search" wire:click="hapusCari">
                                <x-filament::icon icon="heroicon-m-x-mark" /> Hapus pencarian
                            </button>
                        @else
                        <h2>Riwayat Surat</h2>
                        <div class="ps-filters">
                            @php
                                $optPerusahaan = $this->opsiPerusahaan()->map(fn ($n, $id) => ['v' => (string) $id, 'l' => $n])->values()->all();
                                $optUnit = $this->opsiUnit()->map(fn ($n, $id) => ['v' => (string) $id, 'l' => $n])->values()->all();
                                $optJenis = collect(\App\Enum\JenisSurat::cases())->map(fn ($j) => [
                                    'v' => $j->value, 'l' => $j->value.' — '.$j->label(), 'c' => $j->warna()['text'],
                                ])->all();
                            @endphp
                            <x-persuratan.filter-select model="filterPerusahaan" :selected="$filterPerusahaan" :options="$optPerusahaan"
                                placeholder="Semua perusahaan" icon="heroicon-m-building-office-2" />
                            <x-persuratan.filter-select model="filterUnit" :selected="$filterUnit" :options="$optUnit"
                                placeholder="Semua unit" icon="heroicon-m-rectangle-group" :search="true" />
                            <x-persuratan.filter-select model="filterJenis" :selected="$filterJenis" :options="$optJenis"
                                placeholder="Semua jenis surat" icon="heroicon-m-tag" :search="true" align="right" />
                        </div>
                        @endif
                    </div>

                    <div class="ps-list">
                        @forelse ($riwayat as $s)
                            <article @class(['ps-row', 'clickable', 'sel' => $detailId === $s->id]) wire:key="surat-{{ $s->id }}" wire:click="buka({{ $s->id }})" role="button" tabindex="0" x-on:keydown.enter="$wire.buka({{ $s->id }})">
                                <div class="ps-logo-co" style="background: {{ $warna[$s->unit->perusahaan_id % count($warna)] }}" title="{{ $s->unit->perusahaan->nama }}">
                                    {{ $s->unit->perusahaan->kode_surat }}
                                </div>
                                <div class="ps-row-main">
                                    <div class="ps-row-nomor">{{ $s->nomor }}@if ($s->tipe_surat === 'masuk')<em class="ps-badge">Masuk</em>@endif</div>
                                    <div class="ps-row-judul">{{ $s->judul_surat ?: $s->perihal }}</div>
                                    <div class="ps-row-meta">
                                        <x-filament::icon icon="heroicon-m-rectangle-group" class="ps-ico" />
                                        <span class="ps-unit">{{ $s->unit->nama }}</span>
                                        @if ($s->nama_pembuat)
                                            <x-filament::icon icon="heroicon-m-user" class="ps-ico" />
                                            <span>{{ $s->nama_pembuat }}</span>
                                        @endif
                                        @if ($s->jenis)
                                            <span class="ps-jenis" style="background: {{ $s->jenis->warna()['bg'] }}; color: {{ $s->jenis->warna()['text'] }}">{{ $s->jenis->label() }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="ps-row-tgl">
                                    <x-filament::icon icon="heroicon-m-calendar-days" class="ps-ico" />
                                    {{ $s->tanggal->translatedFormat('d M Y') }}
                                </div>
                            </article>
                        @empty
                            <div class="ps-row ps-row-empty">@if ($kataCari !== '')Tidak ada surat yang cocok dengan “{{ $kataCari }}”.@else Tidak ada surat{{ ($filterPerusahaan !== '' || $filterUnit !== '' || $filterJenis !== '') ? ' untuk filter ini' : '' }}.@endif</div>
                        @endforelse
                    </div>

                    <div class="ps-pager">
                        <div class="ps-pager-info">
                            @if ($riwayat->total() > 0)
                                {{ $riwayat->firstItem() }}–{{ $riwayat->lastItem() }} dari {{ number_format($riwayat->total(), 0, ',', '.') }} surat
                            @endif
                        </div>
                        <div class="ps-pager-nav">
                            <select wire:model.live="perHalaman" aria-label="Jumlah per halaman">
                                @foreach ([10, 25, 50] as $n)
                                    <option value="{{ $n }}">{{ $n }} / halaman</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="keHalaman({{ $cur - 1 }})" @disabled($cur <= 1) aria-label="Sebelumnya">‹</button>
                            @foreach ($pages as $i => $p)
                                @if ($i > 0 && $p - $pages[$i - 1] > 1)<span class="gap">…</span>@endif
                                <button type="button" wire:click="keHalaman({{ $p }})" @class(['on' => $p === $cur])>{{ $p }}</button>
                            @endforeach
                            <button type="button" wire:click="keHalaman({{ $cur + 1 }})" @disabled($cur >= $last) aria-label="Berikutnya">›</button>
                        </div>
                    </div>
                </section>

            @if ($detail = $this->detail())
                <div class="ps-drawer-bg" wire:click="tutup" x-data x-on:keydown.escape.window="$wire.tutup()"></div>
                <aside class="ps-drawer" aria-label="Detail surat">
                    <div class="ps-drawer-head">
                        <div>
                            <div class="ps-drawer-nomor">{{ $detail->nomor }}</div>
                            <div class="ps-drawer-badges">
                                @if ($detail->jenis)
                                    <span class="ps-jenis" style="background: {{ $detail->jenis->warna()['bg'] }}; color: {{ $detail->jenis->warna()['text'] }}">{{ $detail->jenis->label() }}</span>
                                @endif
                                <span class="ps-badge">{{ ucfirst($detail->tipe_surat) }}</span>
                                <span class="ps-badge ps-badge-{{ strtolower($detail->status) }}">{{ $detail->status }}</span>
                            </div>
                        </div>
                        <button type="button" class="ps-drawer-x" wire:click="tutup" aria-label="Tutup">
                            <x-filament::icon icon="heroicon-m-x-mark" />
                        </button>
                    </div>

                    <dl class="ps-drawer-body">
                        <div><dt>Judul</dt><dd>{{ $detail->judul_surat ?: '—' }}</dd></div>
                        <div><dt>Perihal</dt><dd>{{ $detail->perihal ?: '—' }}</dd></div>
                        <div><dt>Deskripsi</dt><dd class="pre">{{ $detail->deskripsi ?: '—' }}</dd></div>
                        <div><dt>Perusahaan</dt><dd>{{ $detail->unit->perusahaan->nama }} <small>({{ $detail->unit->perusahaan->kode_surat }})</small></dd></div>
                        <div><dt>Unit</dt><dd>{{ $detail->unit->nama }}@if (filled($detail->unit->kode)) <small>(kode {{ $detail->unit->kode }})</small>@endif</dd></div>
                        <div><dt>Tanggal surat</dt><dd>{{ $detail->tanggal->translatedFormat('l, d F Y') }}</dd></div>
                        <div><dt>Dibuat pada</dt><dd>{{ $detail->created_at?->translatedFormat('d F Y, H:i') ?? '—' }}</dd></div>
                        <div><dt>Pembuat</dt><dd>{{ $detail->nama_pembuat ?: '—' }}@if (! $detail->nama_pembuat && $detail->legacy_user_id) <small>(ID lama #{{ $detail->legacy_user_id }}, tidak ditemukan)</small>@endif</dd></div>
                        @if ($detail->legacy_id)
                            <div><dt>Sumber</dt><dd>Impor sistem lama <small>(ID #{{ $detail->legacy_id }})</small></dd></div>
                        @endif
                    </dl>

                    <div class="ps-drawer-foot" x-data="{ copied: false }">
                        <button type="button" class="ps-btn"
                                x-on:click="navigator.clipboard.writeText(@js($detail->nomor)); copied = true; setTimeout(() => copied = false, 1500)"
                                x-text="copied ? 'Nomor tersalin' : 'Salin nomor surat'"></button>
                    </div>
                </aside>
            @endif
        </div>
    </div>
</x-filament-panels::page>
