<?php

namespace App\Filament\Resources\ManPower\ManpowerRequests\Pages;

use App\Enum\ManpowerRequestType;
use App\Filament\Resources\ManPower\JobPositions\JobPositionResource;
use App\Filament\Resources\ManPower\ManpowerRequests\ManpowerRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListManpowerRequests extends ListRecords
{
    public $type;
    protected static string $resource = ManpowerRequestResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->type = request()->query('type');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(function (): string {
                    $type = $this->type;

                    if ($type === 'quota') {
                        return 'Tambah Perubahan Kuota';
                    } else if ($type === 'employee_change') {
                        return 'Tambah Perubahan Karyawan';
                    }

                    return 'Tambah Perubahan Manpower';
                })
                ->visible(fn () => JobPositionResource::canCreate())
                ->url(fn (): string => $this->getResource()::getUrl('create', ['type' => $this->type])),
        ];
    }

    public function getTitle(): string
    {
        $type = $this->type;

        if ($type === 'quota') {
            return 'Perubahan Kuota';
        } else if ($type === 'employee_change') {
            return 'Perubahan Karyawan';
        }

        return 'Perubahan Manpower';
    }

    public function getTableQuery(): Builder|Relation|null
    {
        $query = ManpowerRequestResource::getEloquentQuery();

        $type = $this->type;
        
        if ($type === 'quota') {
            return $query->where('type', ManpowerRequestType::ADDITIONAL_QUOTA->value);
        } else if ($type === 'employee_change') {
            return $query->where(function (Builder $query) {
                $query->where('type', ManpowerRequestType::ADDITIONAL_EMPLOYEE->value)
                    ->orWhere('type', ManpowerRequestType::EMPLOYEE_REPLACEMENT->value);
            });
        }

        return $query;
    }
}
