<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori Tipe Karyawan (App\Enum\EmployeeTypeCategory: tetap/kontrak/orientasi). Nullable
 * karena tipe karyawan existing belum ada kategorinya - halaman Data Diri cuma membatasi tab
 * untuk yang EKSPLISIT ditandai orientasi, baris lama/null tidak berubah perilakunya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_employee_types', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('m_employee_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
