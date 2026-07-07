<?php

namespace App\Filament\Resources\AttendanceRequests\Pages;

use App\Filament\Resources\AttendanceRequests\AttendanceRequestResource;
use App\Helpers\PermissionHelper;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;

class ListAttendanceRequests extends ListRecords
{
    public $type;
    public $tab;
    public $requestCount = 0;
    public $approvalCount = 0;

    protected static string $resource = AttendanceRequestResource::class;

    public function mount(): void
    {
        parent::mount();
        $this->type = request()->query('type');
        $this->tab = request()->query('tab');
        $this->calculateCounts();
    }

    protected function calculateCounts(): void
    {
        $userId = Auth::id();
        $user = Auth::user();
        
        $requestQuery = AttendanceRequestResource::getEloquentQuery()
            ->where('user_id', $userId)
            ->where('status', 'in_review')
            ->where('type', $this->type);
        
        // Apply company filter if user has global role
        if ($user && PermissionHelper::hasGlobalRole($user) && $user->company_id) {
            $requestQuery->whereHas('user', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }
        
        $this->requestCount = $requestQuery->count();

        $approvalQuery = AttendanceRequestResource::getEloquentQuery()
            ->where('status', 'in_review')
            ->where('type', $this->type);
        
        // Apply company filter if user has global role
        if ($user && PermissionHelper::hasGlobalRole($user) && $user->company_id) {
            $approvalQuery->whereHas('user', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }
        
        $this->approvalCount = $approvalQuery->count();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label($this->getCreateButtonLabel())
                ->visible(fn (): bool => $this->type === 'permission' && AttendanceRequestResource::canCreate())
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

    public function getTabs(): array
    {
        return [
            Tab::make('request')
                ->label('Pengajuan')
                ->badge($this->requestCount > 0 ? (string) $this->requestCount : 0)
                ->modifyQueryUsing(function (Builder $query) {
                    $user = Auth::user();
                    
                    if ($this->type === 'permission') {
                        $query->where('user_id', Auth::id());
                    }
                    
                    if ($user && !PermissionHelper::hasGlobalRole($user) && $user->company_id) {
                        $query->whereHas('user', function ($q) use ($user) {
                            $q->where('company_id', $user->company_id);
                        });
                    }

                    return $query;
                })
                ->visible(fn (): bool => $this->type === 'permission'),

            Tab::make('approval')
                ->label('Persetujuan')
                ->badge($this->approvalCount > 0 ? (string) $this->approvalCount : 0)
                ->modifyQueryUsing(function (Builder $query) {
                    $user = Auth::user();
                    
                    if ($user && !PermissionHelper::hasGlobalRole($user) && $user->company_id) {
                        $query->whereHas('user', function ($q) use ($user) {
                            $q->where('company_id', $user->company_id);
                        });
                    }
                    
                    return $query;
                })
                ->visible(fn (): bool => AttendanceRequestResource::canApprove()),
        ];
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
        $query = AttendanceRequestResource::getEloquentQuery();
        $user = Auth::user();

        if ($this->type && in_array($this->type, ['permission', 'leave', 'overtime'])) {
            $query->where('type', $this->type);
        }
        
        // Apply company filter if user has global role (consistent with table filter default)
        if ($user && !PermissionHelper::hasGlobalRole($user) && $user->company_id) {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }

        return $query;
    }
 }
