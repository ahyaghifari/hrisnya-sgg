<?php

namespace App\Filament\Resources\AttendanceRecords\Pages;

use App\Filament\Resources\AttendanceRecords\AttendanceRecordResource;
use App\Filament\Resources\AttendanceRecords\Schemas\AttendanceUploadForm;
use App\Jobs\ProcessAttendanceImportJob;
use App\Models\Attendance\AttendanceUploads;
use App\Services\Attendance\AttendanceImportService;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

class UploadAttendance extends Page implements HasForms
{
    use InteractsWithForms, WithFileUploads;
    
    protected static string $resource = AttendanceRecordResource::class;

    protected string $view = 'filament.resources.attendance-records.pages.upload-attendance';

    protected static ?string $navigationLabel = 'Upload Absensi';

    protected static ?string $title = 'Upload Data Absensi';

    public ?array $data = [];
    
    public $file_path;

    public function mount(): void
    {
        $this->form->fill();
    }

    // public function debug()
    // {
    //     Notification::make()
    //         ->title('Debug Test')
    //         ->body('Method debug berhasil dipanggil!')
    //         ->info()
    //         ->send();
    // }

    // public function debugForm()
    // {
    //     try {
    //         $data = $this->form->getState();
    //         Notification::make()
    //             ->title('Debug Form')
    //             ->body('Form data: ' . json_encode($data))
    //             ->info()
    //             ->send();
    //     } catch (\Exception $e) {
    //         Notification::make()
    //             ->title('Debug Form Error')
    //             ->body('Error: ' . $e->getMessage())
    //             ->danger()
    //             ->send();
    //     }
    // }

    public function form(Schema $form): Schema
    {
        return AttendanceUploadForm::configure($form)
            ->statePath('data');
    }

    public function processUpload(): void
    {
        // Debug: Show that method is called
        // Notification::make()
        //     ->title('Upload Method Called')
        //     ->body('Upload method berhasil dipanggil!')
        //     ->success()
        //     ->send();

        try {
            // Get validated data from form
            $data = $this->form->getState();
            
            // Notification::make()
            //     ->title('Form Data')
            //     ->body('Data: ' . json_encode($data))
            //     ->info()
            //     ->send();
            
            // Check if file_path exists in data
            if (!isset($data['file_path']) || empty($data['file_path'])) {
                Notification::make()
                    ->title('Error')
                    ->body('Silakan pilih file untuk diupload.')
                    ->danger()
                    ->send();
                return;
            }
            
            // Create attendance upload record
            $uploadData = [
                'file_path' => $data['file_path'],
                'original_name' => basename($data['file_path']),
                'uploaded_by' => Auth::id(),
                'status' => 'pending',
            ];
            
            $attendanceUpload = AttendanceUploads::create($uploadData);
            
            $filePath = storage_path('app/' . $attendanceUpload->file_path);
            $fileSizeInMB = file_exists($filePath) ? filesize($filePath) / 1048576 : 0;
            
            if ($fileSizeInMB > 2) {
                $this->processViaQueue($attendanceUpload);
            } else {
                $this->processImmediately($attendanceUpload);
            }
            
            // Reset form
            $this->form->fill();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Upload')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function processImmediately(AttendanceUploads $record): void
    {
        try {
            $record->update(['status' => 'processing']);
            
            $importService = app(AttendanceImportService::class);
            $result = $importService->import($record);
            
            if ($result['success']) {
                Notification::make()
                    ->title('Import Berhasil')
                    ->body("Data attendance berhasil diimport. Total: {$result['processed']} record, Error: {$result['errors']}")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Import Gagal')
                    ->body($result['message'])
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            $record->update([
                'status' => 'failed',
                'error_log' => ['message' => $e->getMessage()]
            ]);
            
            Notification::make()
                ->title('Import Gagal')
                ->body('Terjadi kesalahan saat mengimport data: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function processViaQueue(AttendanceUploads $record): void
    {
        ProcessAttendanceImportJob::dispatch($record);
        
        Notification::make()
            ->title('Import Dijadwalkan')
            ->body('File Anda sedang diproses di background. Anda akan mendapat notifikasi setelah selesai.')
            ->info()
            ->send();
    }
}