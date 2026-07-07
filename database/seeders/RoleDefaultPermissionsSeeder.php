<?php

namespace Database\Seeders;

use App\Models\Master\Companies;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Default permission per role (selain Super Admin, yang sudah dapat semua permission
 * lewat PermissionsSeeder). Sebelumnya role selain Super Admin mulai dari nol —
 * seeder ini isi baseline yang masuk akal, TETAP bisa diubah manual lewat /admin/roles
 * kapan saja (syncPermissions di sini cuma titik awal, bukan aturan mati).
 *
 * Aman dijalankan ulang (idempotent) — pakai syncPermissions, bukan givePermissionTo,
 * supaya konsisten kalau daftar di bawah diubah lalu di-run lagi.
 */
class RoleDefaultPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'Direktur' => [
                // Oversight lintas modul: view + approval level tinggi, bukan operasional harian.
                'view_struktur_organisasi', 'view_data_pribadi',
                'view_data_absensi', 'view_report_data_absensi',
                'view_pengajuan_absensi', 'approve_pengajuan_absensi',
                'view_pengajuan_pelatihan', 'approve_pengajuan_pelatihan',
                'view_penugasan_individu',
                'view_man_power_planning',
                'view_perubahan_status_karyawan',
                'view_karyawan', 'view_dokumen_karyawan',
                'view_manajemen_lowongan_kerja', 'approve_manajemen_lowongan_kerja',
                'view_penilaian_karyawan',
                'view_master_data_pola_shift', 'view_data_overtime_cost',
                'view_template_shift', 'view_jadwal_shift', 'view_manajemen_tukar_shift',
                'view_coaching', 'view_counseling',
                'view_pengajuan_jasa_dokter', 'view_gaji_trainner',
                'view_master_data_komponen_gaji', 'view_payroll', 'view_doctor_payroll',
                'view_program_incentive',
                'view_lms', 'view_bank_soal', 'view_quiz', 'view_rencana_pelatihan',
                'view_jabatan_dan_kompetensi', 'view_master_data_perusahaan',
                'view_role', 'view_manajemen_persetujuan',
            ],

            'HC Supervisor' => [
                'view_struktur_organisasi',
                'view_data_pribadi', 'edit_data_pribadi',
                'view_data_absensi',
                'view_pengajuan_absensi', 'approve_pengajuan_absensi',
                'view_penugasan_individu',
                'view_man_power_planning',
                'view_perubahan_status_karyawan', 'create_perubahan_status_karyawan',
                'view_karyawan', 'create_karyawan', 'edit_karyawan',
                'view_dokumen_karyawan', 'create_dokumen_karyawan', 'edit_dokumen_karyawan',
                'view_penilaian_karyawan',
                'view_coaching', 'create_coaching', 'edit_coaching',
                'view_counseling', 'create_counseling', 'edit_counseling',
                'view_jabatan_dan_kompetensi',
                'view_manajemen_persetujuan',
            ],

            'HC Manager' => [
                // Semua yang dimiliki HC Supervisor, plus kontrol master data kepegawaian.
                'view_struktur_organisasi',
                'view_data_pribadi', 'edit_data_pribadi',
                'view_data_absensi',
                'view_pengajuan_absensi', 'approve_pengajuan_absensi',
                'view_penugasan_individu',
                'view_man_power_planning', 'create_man_power_planning', 'edit_man_power_planning',
                'view_perubahan_status_karyawan', 'create_perubahan_status_karyawan',
                'edit_perubahan_status_karyawan', 'delete_perubahan_status_karyawan',
                'view_karyawan', 'create_karyawan', 'edit_karyawan', 'delete_karyawan',
                'view_dokumen_karyawan', 'create_dokumen_karyawan', 'edit_dokumen_karyawan', 'delete_dokumen_karyawan',
                'view_manajemen_lowongan_kerja', 'create_manajemen_lowongan_kerja',
                'edit_manajemen_lowongan_kerja', 'approve_manajemen_lowongan_kerja',
                'view_penilaian_karyawan', 'create_penilaian_karyawan', 'edit_penilaian_karyawan',
                'view_coaching', 'create_coaching', 'edit_coaching',
                'view_counseling', 'create_counseling', 'edit_counseling',
                'view_jabatan_dan_kompetensi', 'create_jabatan_dan_kompetensi', 'edit_jabatan_dan_kompetensi',
                'view_master_data_departemen', 'create_master_data_departemen', 'edit_master_data_departemen',
                'view_master_data_divisi', 'create_master_data_divisi', 'edit_master_data_divisi',
                'view_master_data_sub_divisi', 'create_master_data_sub_divisi', 'edit_master_data_sub_divisi',
                'view_master_data_jabatan', 'create_master_data_jabatan', 'edit_master_data_jabatan',
                'view_master_data_pangkat', 'create_master_data_pangkat', 'edit_master_data_pangkat',
                'view_master_data_golongan', 'create_master_data_golongan', 'edit_master_data_golongan',
                'view_master_data_kelas', 'create_master_data_kelas', 'edit_master_data_kelas',
                'view_master_data_jenis_karyawan', 'create_master_data_jenis_karyawan', 'edit_master_data_jenis_karyawan',
                'view_master_data_jenis_cuti', 'create_master_data_jenis_cuti', 'edit_master_data_jenis_cuti',
                'view_master_data_jenis_perizinan', 'create_master_data_jenis_perizinan', 'edit_master_data_jenis_perizinan',
                'view_master_data_jenis_lembur', 'create_master_data_jenis_lembur', 'edit_master_data_jenis_lembur',
                'view_role',
                'view_manajemen_persetujuan', 'create_manajemen_persetujuan', 'edit_manajemen_persetujuan',
            ],

            'Kasubag / Staff Kepegawaian' => [
                'view_struktur_organisasi',
                'view_data_pribadi', 'edit_data_pribadi',
                'view_pengajuan_absensi',
                'view_perubahan_status_karyawan', 'create_perubahan_status_karyawan',
                'view_karyawan', 'create_karyawan', 'edit_karyawan',
                'view_dokumen_karyawan', 'create_dokumen_karyawan', 'edit_dokumen_karyawan',
                'view_man_power_planning',
                'view_jabatan_dan_kompetensi',
            ],

            'Staff Finance' => [
                'view_data_pribadi', 'edit_data_pribadi',
                'view_karyawan',
                'view_data_overtime_cost',
                'view_pengajuan_jasa_dokter', 'create_pengajuan_jasa_dokter', 'edit_pengajuan_jasa_dokter',
                'view_gaji_trainner', 'create_gaji_trainner', 'edit_gaji_trainner',
                'view_master_data_komponen_gaji', 'create_master_data_komponen_gaji', 'edit_master_data_komponen_gaji',
                'view_payroll', 'create_payroll', 'edit_payroll',
                'view_doctor_payroll', 'create_doctor_payroll', 'edit_doctor_payroll',
                'view_program_incentive', 'create_program_incentive', 'edit_program_incentive',
            ],

            'Learning & Development' => [
                'view_data_pribadi', 'edit_data_pribadi',
                'view_karyawan',
                'view_pengajuan_pelatihan', 'approve_pengajuan_pelatihan',
                'create_pengajuan_pelatihan', 'edit_pengajuan_pelatihan',
                'view_lms', 'create_lms', 'edit_lms',
                'view_bank_soal', 'create_bank_soal', 'edit_bank_soal',
                'view_quiz', 'create_quiz', 'edit_quiz',
                'view_rencana_pelatihan', 'create_rencana_pelatihan', 'edit_rencana_pelatihan',
                'view_jabatan_dan_kompetensi',
                'view_master_data_layanan_penugasan',
            ],

            'Manager / Kepala Bidang' => [
                'view_data_pribadi', 'edit_data_pribadi',
                'view_struktur_organisasi',
                'view_karyawan',
                'view_pengajuan_absensi', 'approve_pengajuan_absensi',
                'view_pengajuan_pelatihan', 'approve_pengajuan_pelatihan',
                'view_penugasan_individu', 'edit_penugasan_individu',
                'view_penilaian_karyawan', 'create_penilaian_karyawan', 'edit_penilaian_karyawan',
                'view_man_power_planning',
                'view_coaching', 'create_coaching', 'edit_coaching',
                'view_counseling', 'create_counseling', 'edit_counseling',
                'view_manajemen_lowongan_kerja', 'approve_manajemen_lowongan_kerja',
            ],

            'Supervisor / Kepala Ruangan' => [
                'view_data_pribadi', 'edit_data_pribadi',
                'view_struktur_organisasi',
                'view_karyawan',
                'view_pengajuan_absensi', 'approve_pengajuan_absensi',
                'view_penugasan_individu', 'edit_penugasan_individu',
                'view_penilaian_karyawan',
                'view_coaching', 'create_coaching',
                'view_counseling', 'create_counseling',
            ],

            'Karyawan / Staff' => [
                'view_data_pribadi', 'edit_data_pribadi',
                'view_pengajuan_absensi', 'create_pengajuan_absensi',
                'view_pengajuan_pelatihan', 'create_pengajuan_pelatihan',
                'view_penugasan_individu',
                'view_struktur_organisasi',
            ],
        ];

        Companies::query()
            ->select('id')
            ->chunk(50, function ($companies) use ($map) {
                foreach ($companies as $company) {
                    foreach ($map as $roleName => $permissionNames) {
                        $role = Role::query()
                            ->where('name', $roleName)
                            ->where('guard_name', 'web')
                            ->where('company_id', $company->id)
                            ->first();

                        if (! $role) {
                            continue;
                        }

                        $permissions = Permission::query()
                            ->where('guard_name', 'web')
                            ->whereIn('name', $permissionNames)
                            ->get();

                        $role->syncPermissions($permissions);
                    }
                }
            });
    }
}
