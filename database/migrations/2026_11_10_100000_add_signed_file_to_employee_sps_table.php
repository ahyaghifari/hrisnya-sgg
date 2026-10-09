<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP bertanda tangan langsung (tanpa TTD digital): berkas hasil scan yang diunggah HC setelah SP diterbitkan (FIX).
 * Efek SP (saldo, merit point, notifikasi karyawan) berlaku saat berkas pertama diunggah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_sps', function (Blueprint $table) {
            $table->string('signed_file_path')->nullable();
            $table->string('signed_file_name')->nullable();
            $table->timestamp('signed_uploaded_at')->nullable();
            $table->foreignUlid('signed_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_sps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signed_uploaded_by');
            $table->dropColumn(['signed_file_path', 'signed_file_name', 'signed_uploaded_at']);
        });
    }
};