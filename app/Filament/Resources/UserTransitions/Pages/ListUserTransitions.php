<?php

namespace App\Filament\Resources\UserTransitions\Pages;

use App\Enum\UserTransitionTypeEnum;
use App\Filament\Resources\UserTransitions\UserTransitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListUserTransitions extends ListRecords
{
    protected static string $resource = UserTransitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat ' . (UserTransitionTypeEnum::tryFrom((string) $this->activeTab)?->label() ?? $this->getTitle()))
                ->url(fn (): string => UserTransitionResource::getUrl('create', ['type' => $this->activeTab])),
        ];
    }

    /**
     * Satu halaman buat semua jenis "Perubahan Status Karyawan" (dulu 7 item nav terpisah yang
     * cuma beda `?type=` - lihat riwayat UserTransitionResource::getNavigationItems()), pola
     * sama seperti UserTransitionsPage portal (nav kiri jenis transisi + badge jumlah).
     */
    public function getTabs(): array
    {
        $counts = UserTransitionResource::getEloquentQuery()
            ->reorder()
            ->selectRaw('transition_type, count(*) as total')
            ->groupBy('transition_type')
            ->pluck('total', 'transition_type');

        return collect(UserTransitionTypeEnum::cases())
            ->mapWithKeys(fn (UserTransitionTypeEnum $type) => [
                $type->value => Tab::make($type->label())
                    ->icon($type->icon())
                    ->badge($counts[$type->value] ?? null)
                    ->query(fn ($query) => $query->where('transition_type', $type->value)),
            ])
            ->all();
    }
}
