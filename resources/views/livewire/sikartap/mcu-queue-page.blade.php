<div>
    <div class="page-hero violet" style="margin-bottom:20px;">
        <div class="hero-deco-1"></div><div class="hero-deco-2"></div><div class="hero-deco-3"></div>
        <div style="position:relative;z-index:1;">
            <div class="hidden md:flex" style="align-items:center;gap:6px;margin-bottom:8px;flex-wrap:wrap;">
                <a href="{{ route('dashboard') }}" wire:navigate style="font-size:12px;color:rgba(255,255,255,0.75);text-decoration:none;font-weight:500;">Beranda</a>
                <span style="color:rgba(255,255,255,0.4);">/</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">Sikartap</span>
            </div>
            <h1 style="font-size:20px;font-weight:800;color:#fff;letter-spacing:-0.5px;margin:0 0 6px;">Antrian MCU</h1>
            <p style="font-size:13px;color:rgba(255,255,255,0.85);margin:0;">Pengajuan yang menunggu input Medical Check Up</p>
        </div>
    </div>

    <div class="mb-4" style="display:flex;flex-wrap:wrap;gap:12px;">
        @if ($canFilterByCompany)
            <div style="min-width:220px;">
                <label class="form-label">Perusahaan</label>
                <select wire:model.live="companyId" class="form-input">
                    <option value="">Semua perusahaan</option>
                    @foreach ($this->companyOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div style="min-width:220px;flex:1;">
            <label class="form-label">Cari</label>
            <div style="position:relative;">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-faint);font-size:13px;"></i>
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input" placeholder="Cari nama, NIP..." style="padding-left:38px;">
            </div>
        </div>
    </div>

    @php $antrian = $this->antrian; @endphp

    @if ($antrian->isEmpty())
        <div style="background:var(--surface);border-radius:16px;box-shadow:var(--shadow-card);padding:32px;text-align:center;">
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                {{ $canFilterByCompany && ! $companyId ? 'Pilih perusahaan dulu.' : 'Tidak ada pengajuan yang menunggu input MCU untuk Anda.' }}
            </p>
        </div>
    @else
        {{-- MOBILE: card manual --}}
        <div class="md:hidden">
            <div class="pwa-list">
                @foreach ($antrian as $item)
                    <div class="pwa-list-row" style="align-items:flex-start;">
                        <div style="flex:1;min-width:0;">
                            <p style="font-size:13px;font-weight:600;color:var(--text-heading);">{{ $item->user->name }}</p>
                            <p style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $item->user->nip }} &middot; {{ $item->user->company?->name }}</p>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                                @foreach ($item->mcuActionsFor(auth()->user()) as $action)
                                    <a href="{{ $action['url'] }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;">
                                        <i class="fa-solid {{ $action['icon'] }}"></i> {{ $action['title'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:16px;">
                {{ $antrian->links('livewire.partials.pagination') }}
            </div>
        </div>

        {{-- DESKTOP: tabel --}}
        <div class="hidden md:block">
            <div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="border-bottom:1.5px solid var(--border);">
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Karyawan</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Perusahaan</th>
                            <th style="text-align:left;padding:10px 14px;white-space:nowrap;">Input MCU</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($antrian as $item)
                            <tr wire:key="'mcu-'.{{ $item->id }}" style="border-bottom:1px solid var(--border-soft);">
                                <td style="padding:9px 14px;white-space:nowrap;">
                                    <span style="font-weight:600;">{{ $item->user->name }}</span>
                                    <span style="display:block;font-size:10.5px;color:var(--text-faint);">{{ $item->user->nip }}</span>
                                </td>
                                <td style="padding:9px 14px;white-space:nowrap;">{{ $item->user->company?->name ?? '-' }}</td>
                                <td style="padding:9px 14px;">
                                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                        @foreach ($item->mcuActionsFor(auth()->user()) as $action)
                                            <a href="{{ $action['url'] }}" wire:navigate class="btn-ghost" style="padding:5px 10px;font-size:11px;" title="{{ $action['title'] }}">
                                                <i class="fa-solid {{ $action['icon'] }}"></i> {{ $action['title'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">
                {{ $antrian->links('livewire.partials.pagination') }}
            </div>
        </div>
    @endif
</div>
