<x-filament-panels::page>
    <form wire:submit.prevent="processUpload">
        {{ $this->form }}

        <div class="flex justify-end mt-6 gap-3">
            <x-filament::button type="submit" color="primary">
                Upload & Proses
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>