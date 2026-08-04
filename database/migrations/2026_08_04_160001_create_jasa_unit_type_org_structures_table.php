<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: divisi mana saja yang berhak atas suatu jasa unit (mis. Jasa Perujuk Rawat ->
 * IGD/RANAP/ICU). Lihat planning/2026-08-04-payroll-jasa-unit-per-divisi.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jasa_unit_type_org_structures', function (Blueprint $table) {
            $table->foreignUlid('jasa_unit_type_id')->constrained('jasa_unit_types')->cascadeOnDelete();
            $table->foreignUlid('org_structure_id')->constrained('m_org_structures')->cascadeOnDelete();

            $table->primary(['jasa_unit_type_id', 'org_structure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jasa_unit_type_org_structures');
    }
};
