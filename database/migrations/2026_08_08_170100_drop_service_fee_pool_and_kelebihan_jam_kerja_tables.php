<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bongkar total komponen payroll KELEBIHAN_JAM_KERJA (calculator, enum case, dan tabel
 * pendukungnya - AttendancePolicies sudah lama tidak dibaca kalkulator, digantikan
 * Setting::resolve(); ExtraHourAllowanceRules ikut dihapus karena satu-satunya pemakainya
 * cuma komponen ini) + ServiceFeePool (tidak ada Resource/Service/Livewire yang pernah
 * memakainya sama sekali, sisa rencana lama yang tidak dieksekusi).
 *
 * payroll_items historis yang source_type-nya 'kelebihan_jam_kerja' SENGAJA dibiarkan apa
 * adanya (audit trail slip gaji lama), cuma toggle di company_payroll_components yang
 * dibersihkan supaya tidak muncul lagi sebagai komponen aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_payroll_components')->where('component', 'kelebihan_jam_kerja')->delete();

        Schema::dropIfExists('service_fee_pools');
        Schema::dropIfExists('attendance_policies');
        Schema::dropIfExists('extra_hour_allowance_rules');
    }

    public function down(): void
    {
        // Sengaja tidak di-reverse - tabel & komponennya dihapus permanen dari kode.
    }
};
