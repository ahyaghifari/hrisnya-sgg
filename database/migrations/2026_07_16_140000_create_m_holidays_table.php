<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * company_id NULLABLE = berlaku semua company (mayoritas hari libur nasional/cuti bersama
     * sama untuk semua company), diisi kalau admin sengaja bikin hari libur khusus 1 company.
     */
    public function up(): void
    {
        Schema::create('m_holidays', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('m_companies')->nullOnDelete();
            $table->date('date');
            $table->string('name');
            $table->boolean('is_collective_leave')->default(false)
                ->comment('true = cuti bersama (mengurangi jatah cuti tahunan), false = libur nasional biasa');
            $table->timestamps();

            $table->index(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_holidays');
    }
};
