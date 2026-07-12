<x-filament::section>
    <x-slot name="heading">
        Legenda Kategori
    </x-slot>

    <x-slot name="description">
        Kategori komponen gaji, dikelompokkan berdasarkan pengaruhnya ke gaji.
    </x-slot>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <p class="fi-section-header-heading text-sm font-semibold text-gray-950 dark:text-white">
                Pendapatan
            </p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($earningCategories as $category)
                    <x-filament::badge :color="$category->badgeColor()">
                        {{ $category->label() }}
                    </x-filament::badge>
                @endforeach
            </div>
        </div>

        <div>
            <p class="fi-section-header-heading text-sm font-semibold text-gray-950 dark:text-white">
                Potongan
            </p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($deductionCategories as $category)
                    <x-filament::badge :color="$category->badgeColor()">
                        {{ $category->label() }}
                    </x-filament::badge>
                @endforeach
            </div>
        </div>
    </div>
</x-filament::section>
