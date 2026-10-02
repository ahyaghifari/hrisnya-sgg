<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jabatan yang TIDAK terkena potongan cuti bersama pada kuota Cuti Tahunan (kuota - used saja).
     * Perusahaan ikut dari m_job_positions.company_id.
     */
    public function up(): void
    {
        Schema::create('collective_leave_exempt_positions', function (Blueprint $table) {
            $table->foreignUlid('job_position_id')->primary()->constrained('m_job_positions')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collective_leave_exempt_positions');
    }
};
