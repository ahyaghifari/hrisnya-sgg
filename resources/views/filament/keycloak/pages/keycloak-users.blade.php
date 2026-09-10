<x-filament-panels::page>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem">
        <div style="display:flex;flex-direction:column;gap:.25rem">
            <span style="font-size:.75rem;color:#6b7280">Root Grup</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="filterRootGroup">
                    <option value="">— Semua user —</option>
                    @foreach ($this->rootGroupOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        <div style="display:flex;flex-direction:column;gap:.25rem">
            <span style="font-size:.75rem;color:#6b7280">Sub-grup</span>
            <x-filament::input.wrapper :disabled="! $filterRootGroup">
                <x-filament::input.select wire:model.live="filterSubGroup" :disabled="! $filterRootGroup">
                    <option value="">— Semua di root —</option>
                    @foreach ($this->subGroupOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        @if ($filterRootGroup)
            <x-filament::button size="xs" color="gray" wire:click="resetGroupFilter">
                Reset filter
            </x-filament::button>
        @endif
    </div>

    {{ $this->table }}
</x-filament-panels::page>
