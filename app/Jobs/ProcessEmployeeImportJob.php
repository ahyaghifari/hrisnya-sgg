<?php

namespace App\Jobs;

use App\Imports\Employees\EmployeeImport;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessEmployeeImportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string  $storagePath,
        public readonly ?string $companyId,
        public readonly ?string $importedById,
    ) {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        Log::info("ProcessEmployeeImportJob started: {$this->storagePath}");

        $absolutePath = Storage::disk('local')->path($this->storagePath);
        $import       = new EmployeeImport($this->companyId, $this->importedById);

        try {
            Excel::import($import, $absolutePath);
        } finally {
            Storage::disk('local')->delete($this->storagePath);
        }

        $successCount = $import->getSuccessCount();
        $errors       = $import->getErrors();

        Log::info("ProcessEmployeeImportJob finished: {$successCount} imported, " . count($errors) . ' errors.');

        $user = $this->importedById ? User::find($this->importedById) : null;
        if (! $user) {
            return;
        }

        if (! empty($errors)) {
            $errorList  = implode("\n", array_slice($errors, 0, 10));
            $moreErrors = count($errors) > 10
                ? ("\n... dan " . (count($errors) - 10) . ' error lainnya.')
                : '';

            Notification::make()
                ->title("{$successCount} karyawan berhasil diimport, " . count($errors) . ' baris gagal')
                ->body($errorList . $moreErrors)
                ->warning()
                ->persistent()
                ->sendToDatabase($user);
        } else {
            Notification::make()
                ->title('Import karyawan selesai')
                ->body("{$successCount} karyawan berhasil diimport.")
                ->success()
                ->sendToDatabase($user);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessEmployeeImportJob failed for {$this->storagePath}: " . $exception->getMessage());

        Storage::disk('local')->delete($this->storagePath);

        $user = $this->importedById ? User::find($this->importedById) : null;
        if (! $user) {
            return;
        }

        Notification::make()
            ->title('Import karyawan gagal')
            ->body('Terjadi kesalahan saat memproses file import: ' . $exception->getMessage())
            ->danger()
            ->persistent()
            ->sendToDatabase($user);
    }
}
