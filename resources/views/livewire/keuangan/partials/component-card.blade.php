@php
    $component = $item['component'];
    $required = $component->requiredSettings();
    $isExpanded = $expandedComponent === $component->value;
    $accent = $item['accent'];
@endphp

<div style="background:var(--surface);border-radius:12px;box-shadow:var(--shadow-card);overflow:hidden;border-left:4px solid {{ $accent['solid'] }};{{ $item['disabled'] ? 'opacity:0.5;' : '' }}">
    <div
        @if (count($required) > 0) wire:click="toggleExpand('{{ $component->value }}')" @endif
        style="display:flex;align-items:center;gap:12px;padding:11px 14px;{{ count($required) > 0 ? 'cursor:pointer;' : '' }}"
    >
        <div style="flex:1;min-width:0;">
            <p style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0;">
                <span style="font-size:12px;font-weight:600;color:{{ $accent['solid'] }};">{{ $component->label() }}</span>
                <button type="button" wire:click.stop="openAuditLog('{{ $component->value }}')" title="Riwayat" style="background:none;border:none;color:var(--text-faint);cursor:pointer;font-size:10px;padding:0;">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </button>
            </p>
            @if ($item['disabled'] || $item['alwaysOn'])
                <p style="font-size:10px;color:var(--text-muted);margin:2px 0 0;">
                    @if ($item['disabled'])
                        Belum tersedia — menunggu konfirmasi aturan perhitungan
                    @elseif ($item['alwaysOn'])
                        Selalu aktif otomatis (bukan saklar manual)
                    @endif
                </p>
            @endif
        </div>

        @unless ($item['alwaysOn'])
            <label class="custom-checkbox" wire:click.stop style="--accent:{{ $accent['solid'] }};">
                <input type="checkbox" wire:model.live="active.{{ $component->value }}" @disabled($item['disabled'])>
                <span class="box"><i class="fa-solid fa-check"></i></span>
            </label>
        @endunless

        @if (count($required) > 0)
            <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--text-faint);transition:transform 0.15s;{{ $isExpanded ? 'transform:rotate(180deg);' : '' }}"></i>
        @endif
    </div>

    @if (count($required) > 0 && $isExpanded)
        <div style="background:{{ $accent['soft'] }};padding:12px 14px 14px;border-top:1px dashed {{ $accent['solid'] }}55;">
            <p style="display:flex;align-items:center;gap:6px;font-size:10px;font-weight:700;letter-spacing:0.02em;text-transform:uppercase;color:{{ $accent['solid'] }};margin:0 0 8px;">
                <i class="fa-solid fa-sliders"></i> Pengaturan {{ $component->label() }}
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
                @foreach ($required as $key)
                    {{-- izin_seharian_persen_gaji_pokok cuma relevan buat metode 'gaji_pokok' -
                    disembunyikan kalau company ini pakai metode 'keseluruhan' (tidak dipakai sama
                    sekali di IzinSeharianCalculator::calculateFromKeseluruhan()). --}}
                    @continue ($key === 'izin_seharian_persen_gaji_pokok' && ($settingValues['izin_seharian_metode'] ?? 'keseluruhan') !== 'gaji_pokok')
                    @php $meta = $this->requiredSettingMeta[$key] ?? null; @endphp
                    <div>
                        <label class="form-label" style="font-size:10.5px;display:inline-flex;align-items:center;gap:5px;">
                            {{ $meta?->label ?? $key }}
                            <button type="button" wire:click="openAuditLog('{{ $key }}')" title="Riwayat" style="background:none;border:none;color:var(--text-faint);cursor:pointer;font-size:10px;padding:0;">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </button>
                        </label>
                        @if ($key === 'insentif_metode')
                            <select class="form-input" style="font-size:12px;padding:7px 10px;" wire:model="settingValues.{{ $key }}">
                                <option value="master">Master (tabel Insentif, input manual)</option>
                                <option value="auto">Otomatis (selisih Tunjangan Jabatan lama vs baru)</option>
                            </select>
                        @elseif ($key === 'izin_seharian_metode')
                            <select class="form-input" style="font-size:12px;padding:7px 10px;" wire:model.live="settingValues.{{ $key }}">
                                <option value="keseluruhan">Potong dari Keseluruhan (gross salary)</option>
                                <option value="gaji_pokok">Potong dari Gaji Pokok</option>
                            </select>
                        @elseif ($key === 'bon_metode')
                            <select class="form-input" style="font-size:12px;padding:7px 10px;" wire:model="settingValues.{{ $key }}">
                                <option value="simple">Simple (1 baris nominal per periode)</option>
                                <option value="detail">Detail (rincian potongan satu-satu)</option>
                            </select>
                        @elseif ($key === 'lembur_metode')
                            <select class="form-input" style="font-size:12px;padding:7px 10px;" wire:model="settingValues.{{ $key }}">
                                <option value="per_pangkat">Per Pangkat (rumus lama: base rate / (hari x 7) x multiplier jam)</option>
                                <option value="flat_per_jam">Flat per Jam (rate flat x jam, dihitung sampai menit) — rate diatur di Mapping Komponen Gaji</option>
                            </select>
                        @elseif ($key === 'cuti_melahirkan_metode')
                            <select class="form-input" style="font-size:12px;padding:7px 10px;" wire:model="settingValues.{{ $key }}">
                                <option value="per_komponen">Per Komponen (prorate Tunjangan/Insentif/Jasa Pelayanan)</option>
                                <option value="thp">THP (1 baris potongan dari selisih Take Home Pay)</option>
                            </select>
                        @elseif ($key === 'izin_potongan_per_jam')
                            <label class="custom-checkbox" style="margin-top:4px;">
                                <input type="checkbox" wire:model="settingValues.{{ $key }}" value="1" @checked(($settingValues[$key] ?? '0') === '1')>
                                <span class="box"><i class="fa-solid fa-check"></i></span>
                            </label>
                        @else
                            <input type="{{ $inputType($meta?->type) }}" step="0.01" class="form-input" style="font-size:12px;padding:7px 10px;" wire:model="settingValues.{{ $key }}">
                        @endif
                        @if ($meta?->description)
                            <p style="font-size:9.5px;color:var(--text-muted);margin-top:3px;">{{ $meta->description }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
            <p style="font-size:9.5px;color:var(--text-faint);margin-top:8px;">
                <i class="fa-solid fa-building"></i> Khusus {{ $this->companies->firstWhere('id', $companyId)->name ?? 'perusahaan ini' }} — tidak dibagi ke company lain.
            </p>
        </div>
    @endif
</div>
