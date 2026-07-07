<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * Handle preloading and syncing of role permissions when editing a role.
     */

    /**
     * Temporarily store selected permissions from the form so they can be synced after save.
     *
     * @var array<int|string>|null
     */
    protected ?array $selectedPermissions = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Preload the role's current permissions into the form data before fill.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->getRecord() instanceof Model) {
            // get all permission names for the role and group them by suffix
            $permissionNames = $this->getRecord()->permissions->pluck('name')->toArray();

            foreach ($permissionNames as $permission) {
                // assume permission format like action_resource (e.g. view_role)
                $parts = explode('_', $permission);
                $group = implode('_', array_slice($parts, 1)) ?: 'general';

                $key = $group . '_has_permissions';

                if (! array_key_exists($key, $data)) {
                    $data[$key] = [];
                }

                $data[$key][] = $permission;
            }

            // remove duplicates
            foreach ($data as $k => $v) {
                if (str_ends_with($k, '_has_permissions') && is_array($v)) {
                    $data[$k] = array_values(array_unique($v));
                }
            }
        }

        return $data;
    }

    /**
     * Capture selected permissions from the form before saving so we can sync them after update.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $merged = [];

        foreach ($data as $key => $value) {
            if (str_ends_with($key, '_has_permissions')) {
                if (is_array($value)) {
                    $merged = array_merge($merged, $value);
                }

                unset($data[$key]);
            }
        }

        $this->selectedPermissions = array_values(array_unique($merged));

        return $data;
    }

    /**
     * After saving the role, sync selected permissions to the role.
     */
    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return;
        }

        $record->syncPermissions($this->selectedPermissions ?? []);
    }
}
