<?php

namespace App\Filament\Dokter\Resources\Doctors\Pages;

use App\Enum\Doctor\DoctorDocumentType;
use App\Enum\Doctor\DoctorLicenseType;
use App\Filament\Dokter\Pages\Beranda;
use App\Filament\Dokter\Resources\Doctors\DoctorResource;
use App\Models\Doctor\Doctor;
use App\Services\Doctor\DoctorAccountService;
use App\Services\Doctor\DoctorDocumentService;
use App\Services\Doctor\DoctorLicenseService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateDoctor extends CreateRecord
{
    protected static string $resource = DoctorResource::class;

    protected static ?string $title = 'Tambah Dokter';

    // Dibatasi supaya field tidak membentang selebar layar (panel lebar penuh).
    protected Width | string | null $maxContentWidth = Width::SevenExtraLarge;

    // Tombol Simpan/Batal di kanan bawah form.
    public static string | Alignment $formActionsAlignment = Alignment::End;

    /** Akun HRIS dibuat bersamaan dengan dokter; kalau salah satu gagal, dua-duanya batal (akun HRIS di DB default, dokter di DB `doctor`). */
    protected function handleRecordCreation(array $data): Model
    {
        $accounts = app(DoctorAccountService::class);
        $account = collect($data)->only(['email', 'password', 'company_id'])->all()
            + ['name' => $data['nama'], 'nip' => $data['nip'] ?? null, 'jenis_kelamin' => $data['jenis_kelamin'] ?? null];
        $foto = $data['foto'] ?? null;
        $dokumen = $data['dokumen'] ?? [];
        $lisensi = $data['lisensi'] ?? [];
        $doctorData = collect($data)->except(['email', 'password', 'company_id', 'foto', 'dokumen', 'lisensi'])->all();

        DB::beginTransaction();
        DB::connection('doctor')->beginTransaction();

        try {
            $user = $accounts->create($account);
            // Spesialisasi & penempatan RS disimpan Filament sesudah method ini (saveRelationships).
            $doctor = Doctor::create($doctorData + ['hris_user_id' => $user->id]);

            if ($foto) {
                $accounts->setPhoto($user, $foto);
            }

            DB::connection('doctor')->commit();
            DB::commit();
        } catch (\Throwable $e) {
            DB::connection('doctor')->rollBack();
            DB::rollBack();

            throw $e;
        }

        $this->storeExtras($doctor, $dokumen, $lisensi);

        return $doctor;
    }

    /**
     * Dokumen & lisensi diproses setelah dokter & akun tersimpan; kegagalan satu item tidak membatalkan
     * dokter (bisa ditambah lagi lewat tab Dokumen/Lisensi di halaman edit).
     */
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
