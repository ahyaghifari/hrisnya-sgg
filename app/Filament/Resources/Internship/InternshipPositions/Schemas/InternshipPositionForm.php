<?php

namespace App\Filament\Resources\Internship\InternshipPositions\Schemas;

use App\Enum\JobPositionReqStatus;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Versi ringan JobPositionForm (Master/JobPositions) khusus posisi magang - skip wizard
 * manpower planning/approval yang tidak relevan buat magang. Hasilnya tetap baris m_job_positions
 * biasa (org_structure_id null = Jabatan Fungsional, is_draft=false + status=approved supaya
 * langsung kepakai di ScheduleAssignmentsPage & dropdown Jabatan EmployeeForm, tanpa approval).
 */
class InternshipPositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('company_id')->dehydrated()->default(fn () => Auth::user()->company_id),
                Hidden::make('is_internship')->dehydrated()->default(true),
                Hidden::make('is_draft')->dehydrated()->default(false),
                Hidden::make('status')->dehydrated()->default(JobPositionReqStatus::APPROVED->value),
                TextInput::make('name')
                    ->label('Nama Posisi Magang')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                TextInput::make('actual_manpower')
                    ->label('Kuota Peserta')
                    ->helperText('Batas jumlah karyawan magang yang bisa dipasang ke posisi ini.')
                    ->required()
                    ->numeric()
                    ->minValue(1),
            ]);
    }
}
