<?php

namespace App\Livewire\Rekrutmen\Mcu\Concerns;

use App\Enum\CustomPermissionEnum;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

/**
 * Unggah berkas hasil MCU rekrutmen. Sejak 2026-09-29 semua stasiun MCU rekrutmen cuma mengunggah
 * berkas hasilnya (dokter ikut memilih rekomendasi fit/tidak fit) - rincian per pemeriksaan tidak
 * diisi lagi di sini. MCU Sikartap tabel & halamannya terpisah, tidak terpengaruh.
 *
 * Berkasnya disimpan di disk `local` (BUKAN `public`) karena ini data kesehatan - pola sama dengan
 * slip payroll & dokumen karyawan. Diunduh lewat aksi berpagar izin, tidak lewat URL tebakan.
 */
trait HandlesMcuFileUpload
{
    use WithFileUploads;

    /** Sengaja tanpa tipe: saat berjalan ini TemporaryUploadedFile Livewire, di test UploadedFile palsu. */
    public $file = null;

    /** Berkas yang sudah tersimpan sebelumnya, ditampilkan sebagai tautan unduh. */
    public ?string $existingFilePath = null;

    protected function fileRules(): array
    {
        return [
            // 10 MB: hasil MCU biasanya scan PDF beberapa halaman.
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * Path berkas yang harus disimpan ke kolom file_path. Berkas lama dihapus kalau diganti;
     * kalau tidak ada unggahan baru, yang lama dipertahankan.
     */
    protected function resolveFilePath(string $inviteeId): ?string
    {
        if (! $this->file) {
            return $this->existingFilePath;
        }

        if ($this->existingFilePath) {
            Storage::disk('local')->delete($this->existingFilePath);
        }

        return $this->file->store('mcu-rekrutmen/' . $inviteeId, 'local');
    }

    /** Unduh berkas yang tersimpan - dipagari izin yang sama dengan pengisian stasiunnya. */
    public function downloadFile()
    {
        abort_unless($this->existingFilePath, 404);
        abort_unless(
            Auth::user()->can(CustomPermissionEnum::EDIT_INPUT_MCU->value) || Auth::user()->can($this->stationPermission()),
            403
        );
        abort_unless(Storage::disk('local')->exists($this->existingFilePath), 404);

        return Storage::disk('local')->download($this->existingFilePath);
    }

    /** Izin stasiun ini - dipakai memagari unduhan. */
    abstract protected function stationPermission(): string;
}
