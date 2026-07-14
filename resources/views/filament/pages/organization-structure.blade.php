<x-filament-panels::page>
    {{ $this->form }}

    @php
        $selectedCompanyId = $this->data['company_id'] ?? null;
    @endphp

    @if ($selectedCompanyId)
        @livewire(\App\Livewire\OrganizationChartWidget::class, ['companyId' => $selectedCompanyId])
    @else
        <div class="mt-6 text-center text-gray-500 dark:text-gray-400">
            <x-heroicon-o-information-circle class="w-8 h-8 mx-auto mb-2 opacity-60" />
            <p class="text-sm">Pilih perusahaan terlebih dahulu dari dropdown di atas.</p>
        </div>
    @endif
</x-filament-panels::page>
