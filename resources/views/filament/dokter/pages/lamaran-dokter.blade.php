@php
    $data = $this->daftar();
    $detail = $this->detail();
    $statusLabel = \App\Models\Rekrutmen\DoctorApplication::STATUS;
@endphp
<x-filament-panels::page>
    {{-- CSS biasa: panel ini tanpa theme vite. Gaya kartu sama dengan beranda.blade.php. --}}
    <style>
        .lm-wrap { width: 100%; padding: 4vh 2rem 4rem; box-sizing: border-box; }
        .lm-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1.25rem; }
        .lm-head h1 { margin: 0; font-size: 1.75rem; font-weight: 700; color: #fff; }
        .dk-sel { height: 2.5rem; padding: 0 2rem 0 .875rem; border: 1px solid rgba(255,255,255,.55); border-radius: 9999px; background-color: rgba(255,255,255,.22);
            color: #fff; font-size: .8125rem; font-weight: 500; cursor: pointer; outline: none; }
        .dk-sel option { color: #1e293b; }

        .dk-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem; }
        @media (min-width: 1280px) { .dk-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .dk-card { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 1.25rem 1rem; border: 0; cursor: pointer; font-family: inherit;
            background: rgba(255,255,255,.96); border-radius: 1rem; box-shadow: 0 4px 14px rgba(15,60,100,.12); transition: box-shadow .15s, transform .15s; }
        .dk-card:hover { box-shadow: 0 10px 24px rgba(15,60,100,.22); transform: translateY(-2px); }
        .dk-card:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }
        .dk-card.off { opacity: .65; }
        .dk-name { margin: .75rem 0 0; font-size: .8125rem; font-weight: 700; color: #0f172a; }
        .dk-nip { margin: .125rem 0 0; font-size: .6875rem; color: #64748b; }
        .dk-tags { display: flex; flex-wrap: wrap; justify-content: center; gap: .25rem; margin-top: .625rem; }
        .dk-tag { padding: .125rem .5rem; border-radius: 9999px; background: #ccfbf1; color: #115e59; font-size: .6875rem; font-weight: 600; }
        .dk-tag.gray { background: #f1f5f9; color: #475569; }
        .dk-tag.warn { background: #fef3c7; color: #92400e; }
        .dk-tag.ok { background: #dcfce7; color: #166534; }
        .dk-tag.bad { background: #fee2e2; color: #991b1b; }
        .dk-empty { padding: 2rem; border-radius: 1rem; background: rgba(255,255,255,.96); text-align: center; color: #64748b; font-size: .875rem; }
        .dk-pager { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1.5rem; color: #fff; font-size: .8125rem; }
        .dk-pager button { border: 1px solid rgba(255,255,255,.55); background: rgba(255,255,255,.22); color: #fff; border-radius: 9999px; padding: .4rem 1rem; cursor: pointer; font-size: .8125rem; }
        .dk-pager button:disabled { opacity: .4; cursor: default; }

        .lm-modal { position: fixed; inset: 0; z-index: 60; display: flex; flex-direction: column; background: #f8fafc; }
        .lm-mhead { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem; background: #0f766e; color: #fff; }
        .lm-mhead h3 { margin: 0; font-size: 1.25rem; font-weight: 700; }
        .lm-mhead small { display: block; opacity: .85; font-size: .8125rem; margin-top: .125rem; }
        .lm-mactions { margin-left: auto; display: flex; gap: .5rem; flex-shrink: 0; }
        .lm-btn { display: inline-flex; align-items: center; padding: .5rem 1rem; border-radius: 9999px; border: 1px solid rgba(255,255,255,.6);
            background: rgba(255,255,255,.15); color: #fff; font-size: .8125rem; font-weight: 600; cursor: pointer; }
        .lm-btn.solid { background: #fff; color: #0f766e; border-color: #fff; }
        .lm-btn.danger { background: #fee2e2; color: #991b1b; border-color: #fee2e2; }
        .lm-btn:hover { opacity: .9; }
        .lm-mbody { flex: 1; overflow-y: auto; padding: 1.5rem; }
        .lm-sec { max-width: 72rem; margin: 0 auto; background: #fff; border-radius: .875rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(15,60,100,.1); }
    </style>

    <div class="lm-wrap">
        <div class="lm-head">
            <h1>Lamaran Dokter ({{ $data['total'] }})</h1>
            <select wire:model.live="filterStatus" class="dk-sel" aria-label="Filter status">
                <option value="">Semua status</option>
                @foreach ($statusLabel as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($data['rows']->isEmpty())
            <div class="dk-empty">Belum ada lamaran.</div>
        @else
            <div class="dk-grid">
                @foreach ($data['rows'] as $app)
                    @php
                        $d = $app->data ?? [];
                        $gender = ['Laki-laki' => 'L', 'Perempuan' => 'P'][$d['jenis_kelamin'] ?? ''] ?? null;
                        $tone = match ($app->status) { 'diterima' => 'ok', 'ditolak' => 'bad', 'ditinjau' => 'warn', default => 'gray' };
                    @endphp
                    <button type="button" wire:click="buka({{ $app->id }})" wire:key="lamaran-{{ $app->id }}" class="dk-card {{ $app->status === 'ditolak' ? 'off' : '' }}">
                        <x-dokter.avatar :photo="$this->fotoUrl($app)" :gender="$gender" :size="72" />
                        <p class="dk-name">{{ $app->namaPelamar() }}</p>
                        <p class="dk-nip">{{ $app->company?->name ?? '-' }}</p>
                        <div class="dk-tags">
                            <span class="dk-tag">{{ $d['spesialisasi'] ?? ucfirst($app->jenis) }}</span>
                            <span class="dk-tag {{ $tone }}">{{ $statusLabel[$app->status] ?? ucfirst($app->status) }}</span>
                        </div>
                    </button>
                @endforeach
            </div>

            @if ($data['terakhir'] > 1)
                <div class="dk-pager">
                    <button type="button" wire:click="ke({{ $data['halaman'] - 1 }})" @disabled($data['halaman'] <= 1)>‹ Sebelumnya</button>
                    <span>Halaman {{ $data['halaman'] }} dari {{ $data['terakhir'] }}</span>
                    <button type="button" wire:click="ke({{ $data['halaman'] + 1 }})" @disabled($data['halaman'] >= $data['terakhir'])>Berikutnya ›</button>
                </div>
            @endif
        @endif
    </div>

    @if ($detail)
        <div class="lm-modal" role="dialog" aria-modal="true" aria-label="Detail lamaran" @keydown.escape.window="$wire.tutup()">
            <div class="lm-mhead">
                <x-dokter.avatar :photo="$this->fotoUrl($detail)" :gender="['Laki-laki' => 'L', 'Perempuan' => 'P'][$detail->data['jenis_kelamin'] ?? ''] ?? null" :size="56" />
                <div>
                    <h3>{{ $detail->namaPelamar() }}</h3>
                    <small>{{ $statusLabel[$detail->status] ?? ucfirst($detail->status) }} · masuk {{ $detail->created_at?->translatedFormat('j M Y H:i') }}</small>
                </div>
                <div class="lm-mactions">
                    @if ($detail->status === 'baru')
                        <button type="button" wire:click="tinjau({{ $detail->id }})" class="lm-btn">Tinjau</button>
                    @endif
                    @unless ($detail->isFinal())
                        <button type="button" wire:click="terima({{ $detail->id }})" wire:confirm="Salin data lamaran ke master dokter? Akun login HRIS ditautkan manual lewat Edit dokter." class="lm-btn solid">Terima</button>
                        <button type="button" wire:click="tolak({{ $detail->id }})" wire:confirm="Tolak lamaran ini?" class="lm-btn danger">Tolak</button>
                    @endunless
                    <button type="button" wire:click="tutup" class="lm-btn">Tutup</button>
                </div>
            </div>
            <div class="lm-mbody">
                <div class="lm-sec">
                    @include('filament.dokter.lamaran.detail', ['app' => $detail])
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
