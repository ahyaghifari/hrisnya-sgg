<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Imports\Employees\EmployeeImport;
use App\Jobs\ProcessEmployeeImportJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;
    protected static ?string $title = "Daftar Karyawan";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Karyawan')
                ->visible(EmployeeResource::canCreate()),

            Action::make('download_template')
                ->label('Download Template')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn () => route('employees.import.template'))
                ->openUrlInNewTab()
                ->visible(EmployeeResource::canCreate()),

            Action::make('import_employees')
                ->label('Import Karyawan')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('info')
                ->modalHeading('Import Data Karyawan')
                ->modalDescription('Upload file Excel menggunakan template yang telah didownload. Jika NIP dan nama karyawan sudah ada, data lama akan diganti dengan data baru.')
                ->modalSubmitActionLabel('Import')
                ->schema([
                    FileUpload::make('file')
                        ->label('File Excel (.xlsx)')
                        ->disk('local')
                        ->directory('temp-employee-imports')
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(102400) // 100 MB
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $storagePath = $data['file'];
                    $companyId   = Auth::user()?->company_id;
                    $userId      = Auth::id();
                    $fileSize    = Storage::disk('local')->size($storagePath);

                    // Files larger than 10 MB are processed via queue
                    if ($fileSize > 10 * 1024 * 1024) {
                        ProcessEmployeeImportJob::dispatch($storagePath, $companyId, $userId);

                        Notification::make()
                            ->title('Import sedang diproses')
                            ->body('File besar terdeteksi. Import akan diproses di latar belakang dan Anda akan mendapat notifikasi ketika selesai.')
                            ->info()
                            ->send();

                        return;
                    }

                    // Files ≤ 10 MB: process synchronously
                    $filePath = Storage::disk('local')->path($storagePath);
                    $import   = new EmployeeImport($companyId, $userId);

                    try {
                        Excel::import($import, $filePath);
                    } finally {
                        Storage::disk('local')->delete($storagePath);
                    }

                    $errors       = $import->getErrors();
                    $successCount = $import->getSuccessCount();

                    if (!empty($errors)) {
                        $errorList  = implode("\n", array_slice($errors, 0, 10));
                        $moreErrors = count($errors) > 10 ? ("\n... dan " . (count($errors) - 10) . " error lainnya.") : '';

                        Notification::make()
                            ->title("{$successCount} karyawan berhasil diimport, " . count($errors) . ' baris gagal')
                            ->body($errorList . $moreErrors)
                            ->warning()
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Import berhasil')
                            ->body("{$successCount} karyawan berhasil diimport.")
                            ->success()
                            ->send();
                    }
                })
                ->visible(EmployeeResource::canCreate()),
        ];
    }
}

