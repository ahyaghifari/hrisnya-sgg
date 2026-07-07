<?php

namespace App\Jobs;

use App\Models\Attendance\AttendanceUploads;
use App\Services\Attendance\AttendanceImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAttendanceImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AttendanceUploads $upload
    ) {
        $this->onQueue('imports');
    }

    /**
     * Execute the job.
     */
    public function handle(AttendanceImportService $importService): void
    {
        Log::info("Processing attendance import job for upload ID: {$this->upload->id}");
        
        try {
            $this->upload->update(['status' => 'processing']);
            $importService->import($this->upload);
        } catch (\Exception $e) {
            Log::error("Failed to process attendance import for upload ID: {$this->upload->id}: " . $e->getMessage());
            
            $this->upload->update([
                'status' => 'failed',
                'error_log' => [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ],
                'processed_at' => now(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Attendance import job failed for upload ID: {$this->upload->id}: " . $exception->getMessage());
        
        $this->upload->update([
            'status' => 'failed',
            'error_log' => [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ],
            'processed_at' => now(),
        ]);
    }
}