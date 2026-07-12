<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eligibility tipe karyawan dipindah dari level assignment ke level katalog komponen —
 * 1 komponen gaji cuma punya 1 daftar tipe karyawan eligible, berlaku sama ke semua
 * assignment-nya (bukan beda-beda per pangkat/jabatan yang dituju).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_component_employee_types', function (Blueprint $table) {
            $table->ulid('salary_component_id');
            $table->ulid('employee_type_id');

            $table->foreign('salary_component_id', 'sc_employee_types_component_id_fk')
                ->references('id')->on('salary_components')->cascadeOnDelete();
            $table->foreign('employee_type_id', 'sc_employee_types_employee_type_id_fk')
                ->references('id')->on('m_employee_types')->cascadeOnDelete();

            $table->primary(['salary_component_id', 'employee_type_id']);
        });

        Schema::dropIfExists('salary_component_assignment_employee_types');
    }

    public function down(): void
    {
        Schema::create('salary_component_assignment_employee_types', function (Blueprint $table) {
            $table->ulid('assignment_id');
            $table->ulid('employee_type_id');

            $table->foreign('assignment_id', 'sca_employee_types_assignment_id_fk')
                ->references('id')->on('salary_component_assignments')->cascadeOnDelete();
            $table->foreign('employee_type_id', 'sca_employee_types_employee_type_id_fk')
                ->references('id')->on('m_employee_types')->cascadeOnDelete();

            $table->primary(['assignment_id', 'employee_type_id']);
        });

        Schema::dropIfExists('salary_component_employee_types');
    }
};
