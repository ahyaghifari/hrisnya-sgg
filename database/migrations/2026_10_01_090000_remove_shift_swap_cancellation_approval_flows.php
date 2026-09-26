<?php

use App\Models\ApprovalManagement\MasterApprovalFlows;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Pembatalan tukar shift tidak lagi punya alur master (approver = Kepala Ruangan jadwal).
    public function up(): void
    {
        MasterApprovalFlows::where('module', 'shift_swap_request_cancellation')->delete();
    }

    public function down(): void
    {
        MasterApprovalFlows::onlyTrashed()->where('module', 'shift_swap_request_cancellation')->restore();
    }
};
