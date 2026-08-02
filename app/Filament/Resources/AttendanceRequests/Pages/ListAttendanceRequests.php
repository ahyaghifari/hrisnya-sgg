<?php

namespace App\Filament\Resources\AttendanceRequests\Pages;

use App\Filament\Resources\AttendanceRequests\AttendanceRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListAttendanceRequests extends ListRecords
{
    public $type;

    protected static string $resource = AttendanceRequestResource::class;

    public function mount(): void
    {
        parent::mount();
        $this->type = request()->query('type');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label($this->getCreateButtonLabel())
                ->visible(fn (): bool => in_array($this->type, ['permission', 'leave', 'overtime']) && AttendanceRequestResource::canCreate())
                ->url(fn (): string => AttendanceRequestResource::getUrl('create', ['type' => $this->type])),
        ];
    }

    private function getCreateButtonLabel(): string
    {
        return match ($this->type) {
            'permission' => 'Buat Pengajuan Izin',
            'leave' => 'Buat Pengajuan Cuti',
            'overtime' => 'Buat Pengajuan Lembur',
            default => 'Buat Pengajuan',
        };
    }

    public function getTitle(): string
    {
        return match ($this->type) {
            'permission' => 'Pengajuan Izin',
            'leave' => 'Pengajuan Cuti',
            'overtime' => 'Pengajuan Lembur',
            default => 'Pengajuan',
        };
    }
    
    protected function getTableQuery(): Builder|Relation|null
    {
        // Scoping company sudah ditangani AttendanceRequestResource::getEloquentQuery()
        // (isSuperAdmin()/companyId() dari BaseResource) - tidak perlu diulang di sini.
        $query = AttendanceRequestResource::getEloquentQuery();

        if ($this->type && in_array($this->type, ['permission', 'leave', 'overtime'])) {
            $query->where('type', $this->type);
        }

        return $query;
    }
 }
