@php($hasChildren = count($node['children']) > 0)
@php($isSelected = ($selectedId ?? null) === $node['id'])

<div x-data="{ open: {{ $depth < 1 ? 'true' : 'false' }} }" wire:key="grp-{{ $node['id'] }}">
    <div
        class="fi-kc-group-row"
        style="display:flex;align-items:center;gap:.375rem;padding:.35rem .5rem;border-radius:.375rem;cursor:pointer;padding-inline-start:{{ 0.5 + $depth * 1.25 }}rem;{{ $isSelected ? 'background:rgba(0,102,204,.14);font-weight:600' : '' }}"
        wire:click="selectGroup(@js($node['id']), @js($node['name']))"
    >
        @if ($hasChildren)
            <button type="button" x-on:click.stop="open = ! open"
                style="display:inline-flex;flex:none;color:#9ca3af;background:none;border:0;cursor:pointer">
                <span x-bind:style="open ? 'transform:rotate(90deg)' : ''" style="display:inline-flex;transition:transform .15s">
                    @svg('heroicon-m-chevron-right', '', ['style' => 'width:1rem;height:1rem'])
                </span>
            </button>
        @else
            <span style="display:inline-block;width:1rem;flex:none"></span>
        @endif

        @svg('heroicon-o-user-group', '', ['style' => 'width:1rem;height:1rem;flex:none;color:#9ca3af'])

        <span style="font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $node['name'] }}</span>

        @if ($hasChildren)
            <span style="font-size:.6875rem;color:#9ca3af;margin-inline-start:.25rem">{{ count($node['children']) }}</span>
        @endif
    </div>

    @if ($hasChildren)
        <div x-show="open" x-collapse>
            @foreach ($node['children'] as $child)
                @include('filament.keycloak.pages._group-node', ['node' => $child, 'depth' => $depth + 1, 'selectedId' => $selectedId])
            @endforeach
        </div>
    @endif
</div>

<style>
    .fi-kc-group-row:hover { background: rgba(0,0,0,.05); }
    .dark .fi-kc-group-row:hover { background: rgba(255,255,255,.07); }
</style>
