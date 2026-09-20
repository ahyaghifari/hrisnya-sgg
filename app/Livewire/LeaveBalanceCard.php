<?php

namespace App\Livewire;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Services\Attendance\AttendaceReqService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LeaveBalanceCard extends Component
{
    public string $typeKeyword; // key jenis cuti (nilai LeaveTypeCategoryEnum, mis. "tahunan")
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

        $leaveType = LeaveTypeCategoryEnum::tryFrom($this->typeKeyword);

        $balance = $leaveType
            ? $service->getLeaveUserBalance($userId, $leaveType->value)
            : null;

        return view('livewire.leave-balance-card', ['balance' => $balance]);
    }
}
