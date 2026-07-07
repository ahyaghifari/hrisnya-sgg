<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * Temporarily hold selected permissions from the form so we can sync them after the role is created.
     *
     * @var array<int|string>|null
     */
    protected ?array $selectedPermissions = null;

    /**
     * Capture and remove permissions from the create payload so they are not set as attributes on the Role model.
     * The permissions will be synced after the role is created.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $merged = [];

        foreach ($data as $key => $value) {
            if (str_ends_with($key, '_has_permissions')) {
                if (is_array($value)) {
                    $merged = array_merge($merged, $value);
                }

                // remove the permission arrays from the model attributes
                unset($data[$key]);
            }
        }

        $this->selectedPermissions = array_values(array_unique($merged));

        return $data;
    }

    /**
     * After the role is created, sync the selected permissions to the role using Spatie's syncPermissions.
     */
    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return;
        }
        // always sync (empty array will remove all permissions)
        $record->syncPermissions($this->selectedPermissions ?? []);
    }
}
