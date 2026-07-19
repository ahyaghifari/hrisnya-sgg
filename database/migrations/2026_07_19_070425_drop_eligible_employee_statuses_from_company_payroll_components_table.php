<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy — digantikan `payroll_component_recipients` ("Penerima Komponen Gaji"). Tidak ada
     * UI yang pernah menulis ke kolom ini lagi (selalu null), gate-nya pun sudah dicabut dari
     * PayrollService::runComponent(). Dihapus, bukan cuma dibiarkan mati.
     */
    public function up(): void
    {
        Schema::table('company_payroll_components', function (Blueprint $table) {
            $table->dropColumn('eligible_employee_statuses');
        });
    }

    public function down(): void
    {
        Schema::table('company_payroll_components', function (Blueprint $table) {
            $table->json('eligible_employee_statuses')->nullable();
        });
    }
};
