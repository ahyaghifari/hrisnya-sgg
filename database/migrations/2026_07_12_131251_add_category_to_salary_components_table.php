<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pisahkan "kunci yang dipakai sistem buat cari otomatis" dari "nama tampilan yang bisa
 * berubah-ubah" — sebelumnya PayrollService cocokkan `name` persis (mis. 'Gaji Pokok'),
 * rapuh kalau nama diganti. `category` lebih stabil: 6 kategori 1-nama-persis (dipakai
 * lookup otomatis PayrollService) + 2 kategori umum (TUNJANGAN, BPJS, bisa lebih dari
 * 1 baris, tidak dipakai lookup by name). Lihat App\Enum\Salary\SalaryComponentCategoryEnum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->string('category')->nullable()->after('type');
        });

        $map = [
            'Gaji Pokok' => 'gaji_pokok',
            'Cuti' => 'cuti',
            'Izin' => 'izin',
            'Keterlambatan' => 'keterlambatan',
            'Jasa Pelayanan' => 'jasa_pelayanan',
            'Pajak Jasa Dokter' => 'pajak_jasa_dokter',
            'Tunjangan Jabatan' => 'tunjangan',
            'Tunjangan Transportasi' => 'tunjangan',
            'Tunjangan Masa Kerja' => 'tunjangan',
            'BPJS Kesehatan' => 'bpjs',
            'BPJS Ketenaga Kerjaan' => 'bpjs',
        ];

        foreach ($map as $name => $category) {
            DB::table('salary_components')->where('name', $name)->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
