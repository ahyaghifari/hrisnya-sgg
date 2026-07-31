<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Matikan modul yang belum/tidak dipakai perusahaan tertentu — modul yang dimatikan langsung hilang dari navigasi dan tidak bisa diakses langsung.
    </p>

    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Modul</th>
                    @foreach ($this->companies as $company)
                        <th class="px-4 py-3 text-center font-medium text-gray-600 dark:text-gray-300">{{ $company->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($this->modules as $module)
                    <tr class="border-t border-gray-200 dark:border-white/10">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $module->label() }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $module->description() }}</p>
                        </td>
                        @foreach ($this->companies as $company)
                            <td class="px-4 py-3 text-center">
                                <input
                                    type="checkbox"
                                    wire:model="enabled.{{ $module->value }}.{{ $company->id }}"
                                    class="fi-checkbox-input rounded border-gray-300 text-primary-600 focus:ring-primary-600 dark:border-white/20 dark:bg-white/5"
                                >
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-filament::button wire:click="save" class="mt-4">
        Simpan
    </x-filament::button>
</x-filament-panels::page>
