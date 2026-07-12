<?php

namespace App\Filament\Resources\AdminUsers\Pages;

use App\Filament\Resources\AdminUsers\AdminUserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
use Spatie\Permission\Models\Role;

class ListAdminUsers extends ListRecords
{
    protected static string $resource = AdminUserResource::class;

    protected static ?string $title = 'Admin';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalWidth('md')
                ->mutateFormDataUsing(function (array $data) {
                    if (! Role::where('name', 'Admin')->where('company_id', $data['company_id'] ?? null)->exists()) {
                        Notification::make()
                            ->danger()
                            ->title('Perusahaan ini tidak punya role "Admin"')
                            ->body('Kemungkinan ini company holding (pakai role "Super Admin", bukan "Admin") — akun belum dibuat.')
                            ->send();

                        throw new Halt();
                    }

                    return $data;
                })
                ->after(function (User $record) {
                    $role = Role::where('name', 'Admin')
                        ->where('company_id', $record->company_id)
                        ->firstOrFail();

                    $record->assignRole($role);
                }),
        ];
    }
}
