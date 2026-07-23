@php
    $inputType = fn (?string $type) => in_array($type, ['integer', 'decimal']) ? 'number' : 'text';

    // Palet sama yang dipakai .pwa-menu-grid-icon (navy/green/gold/violet/teal/indigo) — tiap
    // komponen dapat 1 warna aksen (border kiri di baris), di-cycle per index. Versi "soft"
    // (rgba alpha rendah) dipakai buat background panel Pengaturan saat expand — otomatis
    // pas di light & dark mode karena transparan (nempel warna surface di baliknya), bukan
    // token baru.
    $accentPalette = [
        ['solid' => '#042c6c', 'soft' => 'rgba(4,44,108,0.07)'],
        ['solid' => '#047C54', 'soft' => 'rgba(4,124,84,0.07)'],
        ['solid' => '#d97706', 'soft' => 'rgba(217,119,6,0.07)'],
        ['solid' => '#7c3aed', 'soft' => 'rgba(124,58,237,0.07)'],
        ['solid' => '#0f766e', 'soft' => 'rgba(15,118,110,0.07)'],
        ['solid' => '#4f46e5', 'soft' => 'rgba(79,70,229,0.07)'],
    ];
@endphp

<div>
    <x-page-header title="Komponen Gaji" />

    @php
        // Warna aksen dipatok per komponen dari urutan aslinya (bukan urutan sesudah dipecah
        // 2 kolom) — biar 1 komponen selalu pakai warna yang sama terlepas dia jatuh di
        // kolom Pendapatan atau Potongan.
        $componentsWithAccent = collect($this->components)->values()->map(function ($item, $i) use ($accentPalette) {
            $item['accent'] = $accentPalette[$i % count($accentPalette)];
            return $item;
        });
        $earningItems = $componentsWithAccent->filter(fn ($i) => $i['component']->type()->value === 'earning');
        $deductionItems = $componentsWithAccent->filter(fn ($i) => $i['component']->type()->value === 'deduction');
    @endphp

    <div class="mt-4" style="display:flex;flex-direction:column;gap:20px;">
        <div class="doc-type-layout">
            {{-- Kiri: perusahaan — balik ke lebar default .doc-type-nav (210px desktop, full
            width scroll horizontal di mobile). Sesuai urutan tabel (kode), bukan alfabetis. --}}
            @if (auth()->user()->canFilterByCompany())
            <div class="doc-type-nav">
                @foreach ($this->companies as $company)
                    <button type="button" wire:click="selectCompany('{{ $company->id }}')" class="doc-type-btn {{ $companyId === $company->id ? 'is-active' : '' }}">
                        <i class="fa-solid fa-building"></i>
                        <span>{{ $company->name }}</span>
                    </button>
                @endforeach
            </div>
            @endif

            {{-- Kanan: daftar komponen dipecah 2 kolom di desktop (Pendapatan / Potongan),
            numpuk 1 kolom di mobile. Klik kartu buat expand panel "Pengaturan" yang sengaja
            dibuat beda rupa (tint warna sama + heading sendiri). --}}
            <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:16px;">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#15803d;margin:0 0 10px;">
                            <i class="fa-solid fa-arrow-trend-up"></i> Pendapatan
                        </p>
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            @foreach ($earningItems as $item)
                                @include('livewire.keuangan.partials.component-card', ['item' => $item])
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#b91c1c;margin:0 0 10px;">
                            <i class="fa-solid fa-arrow-trend-down"></i> Potongan
                        </p>
                        <div style="display:flex;flex-direction:column;gap:10px;">
                            @foreach ($deductionItems as $item)
                                @include('livewire.keuangan.partials.component-card', ['item' => $item])
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Lapis 3: sisa setting yang belum terikat komponen manapun --}}
                @if (count($flexibleValues) > 0)
                    <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:18px;">
                        <p style="font-size:12.5px;font-weight:700;color:var(--text-heading);margin-bottom:12px;">Pengaturan Lainnya</p>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
                            @foreach ($flexibleValues as $key => $value)
                                @php $meta = $this->flexibleSettingMeta[$key] ?? null; @endphp
                                <div>
                                    <label class="form-label" style="display:inline-flex;align-items:center;gap:5px;">
                                        {{ $meta?->label ?? $key }}
                                        <button type="button" wire:click="openAuditLog('{{ $key }}')" title="Riwayat" style="background:none;border:none;color:var(--text-faint);cursor:pointer;font-size:10px;padding:0;">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                        </button>
                                    </label>
                                    <input type="{{ $inputType($meta?->type) }}" step="0.01" class="form-input" wire:model="flexibleValues.{{ $key }}">
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="btn-primary" style="justify-content:center;align-self:flex-start;padding-left:20px;padding-right:20px;">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Komponen {{ $this->companies->firstWhere('id', $companyId)->name ?? '' }}
                </button>
            </div>
        </div>

        {{-- Lapis 1: Global — selalu company_id NULL, di bawah + save action sendiri. Sengaja
        dikasih rupa yang jelas beda dari kartu komponen di atas (header gradient warna brand,
        bukan aksen per-komponen) — biar langsung kebaca "ini bukan punya 1 company", plus
        strip peringatan karena efeknya ke SEMUA company sekaligus. --}}
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);overflow:hidden;">
            <div style="position:relative;overflow:hidden;padding:16px 18px;background:linear-gradient(135deg, #042c6c 0%, #0b47a1 100%);">
                <div style="position:absolute;top:-22px;right:-10px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.10);"></div>
                <div style="position:absolute;bottom:-26px;left:70px;width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,0.07);"></div>
                <p style="position:relative;z-index:1;display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:#fff;margin:0;">
                    <i class="fa-solid fa-earth-asia"></i> Pengaturan Global
                </p>
                <p style="position:relative;z-index:1;font-size:11.5px;color:rgba(255,255,255,0.8);margin:3px 0 0;">
                    Berlaku ke semua perusahaan sekaligus — bukan cuma yang lagi dipilih di kanan.
                </p>
            </div>

            <div style="padding:18px;">
                @php
                    $globalIcons = [
                        'ump' => 'fa-sack-dollar',
                        'bpjs_kesehatan_rate' => 'fa-heart-pulse',
                        'bpjs_ketenagakerjaan_rate' => 'fa-hard-hat',
                    ];
                @endphp
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                    @foreach (\App\Enum\Payroll\GlobalPayrollSettingEnum::cases() as $g)
                        <div>
                            <label class="form-label" style="display:inline-flex;align-items:center;gap:5px;">
                                {{ $g->label() }}
                                <button type="button" wire:click="openAuditLog('{{ $g->value }}')" title="Riwayat" style="background:none;border:none;color:var(--text-faint);cursor:pointer;font-size:10px;padding:0;">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </button>
                            </label>
                            <div style="position:relative;">
                                <i class="fa-solid {{ $globalIcons[$g->value] ?? 'fa-percent' }}" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:12px;"></i>
                                <input type="number" step="0.01" class="form-input" style="padding-left:32px;" wire:model="globalValues.{{ $g->value }}">
                            </div>
                        </div>
                    @endforeach
                </div>

                <div style="display:flex;align-items:center;gap:10px;background:var(--warn-soft-bg);border:1px solid var(--warn-soft-border);border-radius:10px;padding:10px 14px;margin-top:16px;">
                    <i class="fa-solid fa-triangle-exclamation" style="color:var(--warn-soft-text);flex-shrink:0;"></i>
                    <p style="font-size:11.5px;color:var(--warn-soft-text);margin:0;">Perubahan di sini langsung berlaku ke semua perusahaan tanpa terkecuali.</p>
                </div>

                <button type="button" wire:click="saveGlobal" wire:loading.attr="disabled" wire:target="saveGlobal" class="btn-primary" style="justify-content:center;padding-left:20px;padding-right:20px;margin-top:14px;">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan Global
                </button>
            </div>
        </div>
    </div>

    @include('livewire.partials.audit-log-modal')
</div>
