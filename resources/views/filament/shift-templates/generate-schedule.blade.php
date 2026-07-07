<x-filament-panels::page>
    <form wire:submit.prevent="generate">
        {{ $this->form }}

        <div class="flex justify-start mt-6 gap-3">
            <x-filament::button type="submit" color="success" icon="heroicon-o-bolt">
                Generate Jadwal
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Clusters\ShiftPattern\Resources\ShiftTemplates\ShiftTemplateResource::getUrl('index') }}"
                color="gray"
            >
                Batal
            </x-filament::button>
        </div>
    </form>

    @vite('resources/css/app.css')
</x-filament-panels::page>
