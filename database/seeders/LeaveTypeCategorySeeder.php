<?php

namespace Database\Seeders;

use App\Enum\Attendance\LeaveTypeCategoryEnum;
use App\Models\Master\Attendance\LeaveTypes;
use App\Models\Master\Companies;
use Illuminate\Database\Seeder;

/**
 * Backfill kolom `m_leave_types.category` + pastikan tiap company punya baris untuk
 * 4 kategori LeaveTypeCategoryEnum (tahunan/haid/melahirkan/keguguran).
 *
 * - Baris existing yang namanya cocok label kategori & category-nya masih kosong -> diisi.
 * - Kategori yang belum ada barisnya -> dibuat pakai default UU (defaultQuotaDays()).
 * Idempoten: aman dijalankan berulang.
 */
class LeaveTypeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $resetPeriod = [
            'yearly' => 'yearly',
            'monthly' => 'monthly',
            'per_request' => 'none',
        ];

        foreach (Companies::all() as $company) {
            foreach (LeaveTypeCategoryEnum::cases() as $cat) {
                $existing = LeaveTypes::where('company_id', $company->id)
                    ->where(fn ($q) => $q->where('category', $cat->value)->orWhere('name', $cat->label()))
                    ->first();

                if ($existing) {
                    if (blank($existing->category)) {
                        $existing->update(['category' => $cat->value]);
                    }

                    continue;
                }

                LeaveTypes::create([
                    'company_id' => $company->id,
                    'name' => $cat->label(),
                    'category' => $cat->value,
                    'quota_days' => $cat->defaultQuotaDays(),
                    'reset_period' => $resetPeriod[$cat->usageWindow()],
                    'is_paid' => true,
                ]);
            }
        }
    }
}
