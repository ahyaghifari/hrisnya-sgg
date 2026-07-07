<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Daftar permission default
        $permissions = [
            // Organzation Structure Permissions
            'view_struktur_organisasi',
            'create_struktur_organisasi',

            // Personal Data Permissions
            'view_data_pribadi',
            'edit_data_pribadi',

            // Attendance Records Permissions
            'view_data_absensi',
            'view_report_data_absensi',
            'create_data_absensi',
            'edit_data_absensi',
            'delete_data_absensi',
            'import_data_absensi',

            // Attendance Requests Permissions
            'view_pengajuan_absensi',
            'create_pengajuan_absensi',
            'approve_pengajuan_absensi',

            // Training Request Permissions
            'view_pengajuan_pelatihan',
            'approve_pengajuan_pelatihan',
            'create_pengajuan_pelatihan',
            'edit_pengajuan_pelatihan',
            'delete_pengajuan_pelatihan',

            // Task Request Permissions
            'view_penugasan_individu',
            'edit_penugasan_individu',
            'delete_penugasan_individu',

            // Man Power Planning Permissions
            'view_man_power_planning',
            'create_man_power_planning',
            'edit_man_power_planning',
            'delete_man_power_planning',

            // User Transactions Permissions
            'view_perubahan_status_karyawan',
            'create_perubahan_status_karyawan',
            'edit_perubahan_status_karyawan',
            'delete_perubahan_status_karyawan',

            // Employee Permissions
            'view_karyawan',
            'create_karyawan',
            'edit_karyawan',
            'delete_karyawan',

            // Employee Documents Permissions
            'view_dokumen_karyawan',
            'create_dokumen_karyawan',
            'edit_dokumen_karyawan',
            'delete_dokumen_karyawan',

            // Job Vacancy Permissions
            'view_manajemen_lowongan_kerja',
            'create_manajemen_lowongan_kerja',
            'edit_manajemen_lowongan_kerja',
            'delete_manajemen_lowongan_kerja',
            'approve_manajemen_lowongan_kerja',

            // User Assessment Permissions
            'view_penilaian_karyawan',
            'create_penilaian_karyawan',
            'edit_penilaian_karyawan',
            'delete_penilaian_karyawan',

            // Master Data Pola Shift Permissions
            'view_master_data_pola_shift',
            'create_master_data_pola_shift',
            'edit_master_data_pola_shift',
            'delete_master_data_pola_shift',

            // Overtime Cost Permissions
            'view_data_overtime_cost',
            'create_data_overtime_cost',
            'edit_data_overtime_cost',
            'delete_data_overtime_cost',

            // Template Shift Permissions
            'view_template_shift',
            'create_template_shift',
            'edit_template_shift',
            'delete_template_shift',

            // Jadwal Shift Permissions
            'view_jadwal_shift',
            'create_jadwal_shift',
            'edit_jadwal_shift',
            'delete_jadwal_shift',

            // Swap Shift Permissions
            'view_manajemen_tukar_shift',
            'create_manajemen_tukar_shift',
            'edit_manajemen_tukar_shift',
            'delete_manajemen_tukar_shift',

            // Coaching Permissions
            'view_coaching',
            'create_coaching',
            'edit_coaching',
            // 'delete_coaching',

            // Counseling Permissions
            'view_counseling',
            'create_counseling',
            'edit_counseling',
            // 'delete_counseling',

            // Doctor Fee Submission Permissions
            'view_pengajuan_jasa_dokter',
            'create_pengajuan_jasa_dokter',
            'edit_pengajuan_jasa_dokter',
            'delete_pengajuan_jasa_dokter',

            // Trainner Fee Submission Permissions
            'view_gaji_trainner',
            'create_gaji_trainner',
            'edit_gaji_trainner',
            'delete_gaji_trainner',

            // Master Data Salary Permissions
            'view_master_data_komponen_gaji',
            'create_master_data_komponen_gaji',
            'edit_master_data_komponen_gaji',
            'delete_master_data_komponen_gaji',

            // Payroll Permissions
            'view_payroll',
            'create_payroll',
            'edit_payroll',
            'delete_payroll',

            // Doctor Payroll Permissions
            'view_doctor_payroll',
            'create_doctor_payroll',
            'edit_doctor_payroll',
            'delete_doctor_payroll',

            // Program Incentive Permissions
            'view_program_incentive',
            'create_program_incentive',
            'edit_program_incentive',
            'delete_program_incentive',

            // LMS Permissions
            'view_lms',
            'create_lms',
            'edit_lms',
            'delete_lms',

            // BANK Soal Permissions
            'view_bank_soal',
            'create_bank_soal',
            'edit_bank_soal',
            'delete_bank_soal',

            // Quiz Permissions
            'view_quiz',
            'create_quiz',
            'edit_quiz',
            'delete_quiz',

            // LMS Plan Permissions
            'view_rencana_pelatihan',
            'create_rencana_pelatihan',
            'edit_rencana_pelatihan',
            'delete_rencana_pelatihan',

            // Jabatan & Kompetensi Permissions
            'view_jabatan_dan_kompetensi',
            'create_jabatan_dan_kompetensi',
            'edit_jabatan_dan_kompetensi',
            'delete_jabatan_dan_kompetensi',

            // Company Permissions
            'view_master_data_perusahaan',
            'create_master_data_perusahaan',
            'edit_master_data_perusahaan',
            'delete_master_data_perusahaan',

            // Department Permissions
            'view_master_data_departemen',
            'create_master_data_departemen',
            'edit_master_data_departemen',
            'delete_master_data_departemen',

            // Divisions Permissions
            'view_master_data_divisi',
            'create_master_data_divisi',
            'edit_master_data_divisi',
            'delete_master_data_divisi',

            // Sub Divisions Permissions
            'view_master_data_sub_divisi',
            'create_master_data_sub_divisi',
            'edit_master_data_sub_divisi',
            'delete_master_data_sub_divisi',

            // Jabatan Permissions
            'view_master_data_jabatan',
            'create_master_data_jabatan',
            'edit_master_data_jabatan',
            'delete_master_data_jabatan',

            // Pangkat Permissions
            'view_master_data_pangkat',
            'create_master_data_pangkat',
            'edit_master_data_pangkat',
            'delete_master_data_pangkat',

            // Golongan Permissions
            'view_master_data_golongan',
            'create_master_data_golongan',
            'edit_master_data_golongan',
            'delete_master_data_golongan',

            // Kelas Permissions
            'view_master_data_kelas',
            'create_master_data_kelas',
            'edit_master_data_kelas',
            'delete_master_data_kelas',

            // Employee Type Permissions
            'view_master_data_jenis_karyawan',
            'create_master_data_jenis_karyawan',
            'edit_master_data_jenis_karyawan',
            'delete_master_data_jenis_karyawan',

            // Religions Permissions
            'view_master_data_agama',
            'create_master_data_agama',
            'edit_master_data_agama',
            'delete_master_data_agama',

            // Bank Permissions
            'view_master_data_bank',
            'create_master_data_bank',
            'edit_master_data_bank',
            'delete_master_data_bank',

            // Leave Type Permissions
            'view_master_data_jenis_cuti',
            'create_master_data_jenis_cuti',
            'edit_master_data_jenis_cuti',
            'delete_master_data_jenis_cuti',

            // Permission Type Permissions
            'view_master_data_jenis_perizinan',
            'create_master_data_jenis_perizinan',
            'edit_master_data_jenis_perizinan',
            'delete_master_data_jenis_perizinan',

            // Overtime Type Permissions
            'view_master_data_jenis_lembur',
            'create_master_data_jenis_lembur',
            'edit_master_data_jenis_lembur',
            'delete_master_data_jenis_lembur',

            // Task Service Permissions
            'view_master_data_layanan_penugasan',
            'create_master_data_layanan_penugasan',
            'edit_master_data_layanan_penugasan',
            'delete_master_data_layanan_penugasan',

            // Roles Permissions
            'view_role',
            'create_role',
            'edit_role',
            'delete_role',

            // Permission (katalog permission itu sendiri) Permissions
            'view_permission',
            'create_permission',
            'edit_permission',
            'delete_permission',

            // Approval Management Permissions
            'view_manajemen_persetujuan',
            'create_manajemen_persetujuan',
            'edit_manajemen_persetujuan',
            'delete_manajemen_persetujuan',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        $allPermissions = Permission::where('guard_name', 'web')->get();

        Role::where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->chunk(50, function ($roles) use ($allPermissions) {
                foreach ($roles as $role) {
                    $role->syncPermissions($allPermissions);
                }
            });
    }
}
