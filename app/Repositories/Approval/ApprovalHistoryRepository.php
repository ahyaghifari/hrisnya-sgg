<?php

namespace App\Repositories\Approval;

use App\Models\ApprovalManagement\ApprovalHistories;

class ApprovalHistoryRepository
{
    public function create(array $data): ApprovalHistories
    {
        $history =  ApprovalHistories::create($data);
        return $history->refresh();
    }
}
