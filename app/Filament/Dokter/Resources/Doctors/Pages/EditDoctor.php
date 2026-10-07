<?php

namespace App\Filament\Dokter\Resources\Doctors\Pages;

use App\Filament\Dokter\Pages\Beranda;
use App\Filament\Dokter\Resources\Doctors\DoctorResource;
use App\Services\Doctor\DoctorAccountService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class EditDoctor extends EditRecord
{
    protected static string $resource = DoctorResource::class;

    // Dibatasi supaya field tidak membentang selebar layar (panel lebar penuh).
    protected Width | string | null $maxContentWidth = Width::SevenExtraLarge;

    // Tombol Simpan/Batal di kanan bawah form.
    public static string | Alignment $formActionsAlignment = Alignment::End;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->successRedirectUrl(Beranda::getUrl())];
    }

    /** Foto ada di akun HRIS dokter (users.profile_picture), bukan di Doctor DB. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['foto'] = $this->getRecord()->hrisUser()?->profilePhotoPath();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $foto = $data['foto'] ?? null;
        unset($data['foto']);

        if ($user = $this->getRecord()->hrisUser()) {
            app(DoctorAccountService::class)->setPhoto($user, is_string($foto) && $foto !== '' ? $foto : null);
        }

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return Beranda::getUrl();
    }
}
