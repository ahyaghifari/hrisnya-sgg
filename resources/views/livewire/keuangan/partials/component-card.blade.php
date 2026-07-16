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
                    @php $meta = $this->requiredSettingMeta[$key] ?? null; @endphp
                    <div>
                        <label class="form-label" style="font-size:10.5px;">{{ $meta?->label ?? $key }}</label>
                        <input type="{{ $inputType($meta?->type) }}" step="0.01" class="form-input" style="font-size:12px;padding:7px 10px;" wire:model="settingValues.{{ $key }}">
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
