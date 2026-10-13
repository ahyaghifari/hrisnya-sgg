<?php

namespace App\Filament\Dokter\Resources\Doctors\Pages;

use App\Filament\Dokter\Pages\Beranda;
use App\Filament\Dokter\Resources\Doctors\DoctorResource;
use App\Enum\Doctor\DoctorDocumentType;
use App\Models\User;
use App\Services\Doctor\DoctorAccountService;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        // Dokter yang sudah ditambahkan tidak dihapus (riwayat lisensi/credentialing/dokumen dijaga): cukup dinonaktifkan.
        return [
            Action::make('nonaktifkan')->label('Nonaktifkan')->icon('heroicon-o-no-symbol')->color('danger')
                ->visible(fn () => $this->getRecord()->status === 'aktif')
                ->requiresConfirmation()->modalDescription('Dokter disembunyikan dari daftar aktif. Semua data dan riwayatnya tetap tersimpan.')
                ->action(function () {
                    $this->getRecord()->update(['status' => 'nonaktif']);
                    $this->redirect(Beranda::getUrl());
                }),
            Action::make('aktifkan')->label('Aktifkan')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn () => $this->getRecord()->status !== 'aktif')
                ->requiresConfirmation()
                ->action(function () {
                    $this->getRecord()->update(['status' => 'aktif']);
                    $this->redirect(Beranda::getUrl());
                }),
        ];
    }

    /** Foto ada di akun HRIS dokter (users.profile_picture), bukan di Doctor DB. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['foto'] = $this->getRecord()->hrisUser()?->profilePhotoPath();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Field Foto & Akun HRIS di-hold (tidak ada di form): jangan sentuh foto akun yang sudah tertaut.
        if (! array_key_exists('foto', $data)) {
            return $data;
        }

        $foto = $data['foto'] ?? null;
        unset($data['foto']);

        // Akun yang baru ditautkan di form ini belum ada di record, jadi ambil dari $data dulu.
        $user = filled($data['hris_user_id'] ?? null)
            ? User::withoutGlobalScopes()->find($data['hris_user_id'])
            : $this->getRecord()->hrisUser();

        if ($user) {
            $foto = is_string($foto) && $foto !== '' ? $foto : null;

            // Akun belum punya foto & form kosong: pakai pas foto dari lamaran (dokumen PAS_FOTO) kalau ada.
            if (! $foto && ! $user->profilePhotoPath()) {
                $foto = $this->fotoDariLamaran();
            }

            app(DoctorAccountService::class)->setPhoto($user, $foto);
        }

        return $data;
    }

    /** Salin pas foto lamaran (terenkripsi di disk privat) ke disk public sebagai foto profil; null kalau tidak ada. */
    private function fotoDariLamaran(): ?string
    {
        $doc = $this->getRecord()->documents()->where('document_type', DoctorDocumentType::PAS_FOTO->value)->latest()->first();

        if (! $doc || ! Storage::disk('local')->exists($doc->file_path)) {
            return null;
        }

        $path = 'profile-pictures/'.Str::ulid().'.'.(pathinfo($doc->file_name, PATHINFO_EXTENSION) ?: 'jpg');
        Storage::disk('public')->put($path, Crypt::decryptString(Storage::disk('local')->get($doc->file_path)));

        return $path;
    }

    protected function getRedirectUrl(): ?string
    {
        return Beranda::getUrl();
    }
}
