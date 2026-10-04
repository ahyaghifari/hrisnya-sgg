<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis penjamin per kategori omset (App\Enum\Payroll\OmsetPayerType): bpjs = 2,5% omset,
 * umum = 7,5% omset jadi dasar Jasa Pelayanan. Kategori lama default 'umum' - admin perlu
 * mengecek ulang & set BPJS di Mapping Komponen Gaji sebelum generate payroll berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omset_categories', function (Blueprint $table) {
            $table->string('payer_type')->default('umum')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('omset_categories', function (Blueprint $table) {
            $table->dropColumn('payer_type');
        });
    }
};
