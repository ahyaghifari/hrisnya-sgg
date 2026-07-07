<?php

namespace App\Livewire;

use App\Models\Master\Attendance\LeaveTypes;
use App\Models\User;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LeaveBalanceCard extends Component
{
    public string $typeKeyword;
    public string $label;
    public string $icon;
    public string $color;
    public string $iconBg;
    public string $gridClass;

    /** Opsional — kalau diisi, tampilkan saldo milik user ini, bukan yang sedang login. */
    public ?string $userId = null;

    public bool $compact = false;

    public function render(AttendaceReqService $service)
    {
        $userId = $this->userId ?? Auth::id();
        $companyId = $this->userId ? User::find($this->userId)?->company_id : Auth::user()->company_id;

        $leaveType = $companyId
            ? LeaveTypes::where('company_id', $companyId)
                ->where('name', 'like', "%{$this->typeKeyword}%")
                ->first()
            : null;

        $balance = $leaveType
            ? $service->getLeaveUserBalance($userId, $leaveType->id)
            : null;

        return view('livewire.leave-balance-card', ['balance' => $balance]);
    }
}
