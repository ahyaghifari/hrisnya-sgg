<?php

namespace App\Filament\Dokter\Resources\Doctors\Pages;

use App\Enum\Doctor\DoctorDocumentType;
use App\Enum\Doctor\DoctorLicenseType;
use App\Filament\Dokter\Pages\Beranda;
use App\Filament\Dokter\Resources\Doctors\DoctorResource;
use App\Models\Doctor\Doctor;
use App\Services\Doctor\DoctorDocumentService;
use App\Services\Doctor\DoctorLicenseService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class CreateDoctor extends CreateRecord
{
    protected static string $resource = DoctorResource::class;

    protected static ?string $title = 'Tambah Dokter';

    // Dibatasi supaya field tidak membentang selebar layar (panel lebar penuh).
    protected Width | string | null $maxContentWidth = Width::SevenExtraLarge;

    // Tombol Simpan/Batal di kanan bawah form.
    public static string | Alignment $formActionsAlignment = Alignment::End;

    /**
     * Akun login HRIS & foto di-hold: dokter dibuat tanpa akun (tautkan nanti). Dokumen & lisensi diproses
     * setelah dokter tersimpan; spesialisasi, penempatan, dan SIP disimpan Filament (saveRelationships).
     */
    protected function handleRecordCreation(array $data): Model
    {
        $dokumen = $data['dokumen'] ?? [];
        $lisensi = $data['lisensi'] ?? [];

        $doctor = Doctor::create(collect($data)->except(['dokumen', 'lisensi'])->all());

        $this->storeExtras($doctor, $dokumen, $lisensi);

        return $doctor;
    }

    /** Kegagalan satu item dokumen/lisensi tidak membatalkan dokter (bisa ditambah lagi lewat tab di halaman edit). */
    private function storeExtras(Doctor $doctor, array $dokumen, array $lisensi): void
    {
        $gagal = [];

        foreach ($dokumen as $item) {
            try {
                app(DoctorDocumentService::class)->store($doctor, collect($item)->except('file')->all(), $item['file']);
            } catch (\Throwable $e) {
                report($e);
                $gagal[] = DoctorDocumentType::tryFrom($item['document_type'] ?? '')?->getLabel() ?? 'Dokumen';
            }
        }

        foreach ($lisensi as $item) {
            try {
                app(DoctorLicenseService::class)->store($doctor, collect($item)->except('file')->all(), $item['file'] ?? null);
            } catch (\Throwable $e) {
                report($e);
                $gagal[] = DoctorLicenseType::tryFrom($item['jenis'] ?? '')?->getLabel() ?? 'Lisensi';
            }
        }

        if ($gagal) {
            Notification::make()->warning()->title('Dokter tersimpan, tapi sebagian dokumen/lisensi gagal disimpan')
                ->body(implode(', ', $gagal).'. Tambahkan lagi lewat tab Dokumen/Lisensi di halaman edit.')->persistent()->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return Beranda::getUrl();
    }
}
