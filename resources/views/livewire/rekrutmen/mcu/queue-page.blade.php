<div>
    <div class="page-hero" style="background:linear-gradient(130deg,#6e561f 0%,#98782d 100%);margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Antrian MCU Rekrutmen</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Pelamar yang sudah diundang Medical Check Up</p>
        </div>
    </div>

    <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
        <div style="min-width:260px;">
            <label class="form-label">Perusahaan</label>
            <select wire:model.live="companyId" class="form-input">
                <option value="">-- Pilih perusahaan --</option>
                @foreach ($this->companyOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @php $antrian = $this->antrian; @endphp

    @if ($antrian->isEmpty())
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                {{ ! $companyId ? 'Pilih perusahaan dulu.' : 'Belum ada pelamar yang diundang MCU di perusahaan ini.' }}
            </p>
        </div>
    @else
        {{-- MOBILE --}}
        <div class="md:hidden">
            <div class="pwa-list">
                @foreach ($antrian as $app)
                    <div class="pwa-list-row" style="align-items:flex-start;">
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $app->answersMap()['full_name'] ?? '—' }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0;">{{ $app->jobOpening?->title ?? '—' }}</p>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                                @foreach ($this->stationsFor($app) as $st)
                                    <a href="{{ $st['url'] }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;">
                                        <i class="fa-solid {{ $st['icon'] }}"></i> {{ $st['label'] }}
                                        @if ($st['done']) <i class="fa-solid fa-check" style="color:var(--success-text);"></i> @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- DESKTOP --}}
        <div class="hidden md:block">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Pelamar</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Lowongan</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Isi MCU</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($antrian as $app)
                            <tr wire:key="mcu-{{ $app->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;white-space:nowrap;font-weight:600;">{{ $app->answersMap()['full_name'] ?? '—' }}</td>
                                <td style="padding:9px 14px;color:var(--text-muted);">{{ $app->jobOpening?->title ?? '—' }}</td>
                                <td style="padding:9px 14px;">
                                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                        @foreach ($this->stationsFor($app) as $st)
                                            <a href="{{ $st['url'] }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="{{ $st['label'] }}">
                                                <i class="fa-solid {{ $st['icon'] }}"></i> {{ $st['label'] }}
                                                @if ($st['done']) <i class="fa-solid fa-check" style="color:var(--success-text);"></i> @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
