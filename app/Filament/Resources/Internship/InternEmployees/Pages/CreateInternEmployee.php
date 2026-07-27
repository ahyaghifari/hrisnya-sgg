<?php

namespace App\Filament\Resources\Internship\InternEmployees\Pages;

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Internship\InternEmployees\InternEmployeeResource;

/**
 * Reuse penuh CreateEmployee (restore soft-deleted user by email/nip, sync roles, bikin
 * UserJobPositions dari profile.job_position_id) - satu-satunya beda cuma resource & judul.
 * Admin tetap harus pilih Jabatan yang ditandai "Posisi Magang" (InternshipPositionResource)
 * di form supaya karyawan ini muncul di list ini setelah disimpan.
 */
class CreateInternEmployee extends CreateEmployee
{
    protected static string $resource = InternEmployeeResource::class;
    protected static ?string $title = 'Tambah Karyawan Magang';
}
