<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->unique();

            $table->string('nickname')->nullable();
            $table->string('gender')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('no_nik')->nullable();
            $table->string('no_kk')->nullable();
            $table->string('no_bpjs_kes')->nullable();
            $table->string('no_bpjs_ket')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('npwp')->nullable();

            $table->string('country')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('village')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('rt')->nullable();
            $table->string('rw')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();

            // Foreign keys
            $table->ulid('religion_id')->nullable();
            $table->ulid('bank_id')->nullable();
            $table->ulid('org_structure_id')->nullable();
            $table->ulid('job_position_id')->nullable();
            $table->ulid('pangkat_id')->nullable();
            $table->ulid('golongan_id')->nullable();
            $table->ulid('kelas_id')->nullable();
            $table->ulid('employee_type_id')->nullable();
            $table->ulid('business_unit_id')->nullable();

            $table->json('profile_picture')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('religion_id')->references('id')->on('m_religions')->nullOnDelete();
            $table->foreign('bank_id')->references('id')->on('m_banks')->nullOnDelete();
            $table->foreign('org_structure_id')->references('id')->on('m_org_structures')->nullOnDelete();
            $table->foreign('job_position_id')->references('id')->on('m_job_positions')->nullOnDelete();
            $table->foreign('pangkat_id')->references('id')->on('m_pangkats')->nullOnDelete();
            $table->foreign('golongan_id')->references('id')->on('m_golongans')->nullOnDelete();
            $table->foreign('kelas_id')->references('id')->on('m_kelas')->nullOnDelete();
            $table->foreign('employee_type_id')->references('id')->on('m_employee_types')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
