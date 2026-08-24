<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat kontrak (scan/PDF) sekarang nempel langsung di baris kontraknya, BUKAN lagi lewat
 * UserDocumentType::EMPLOYMENT_CONTRACT (dihapus, lihat migration
 * 2026_08_22_110500_drop_employment_contract_user_documents.php) - 1 baris user_contracts =
 * 1 term kontrak, jadi 1 tempat wajar buat nempel suratnya juga (2026-08-22, instruksi user:
 * "harusnya surat kontrak ya di tabel kontraknya karyawan langsung"). Nullable - baris lama/baru
 * boleh belum ada suratnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_contracts', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('contract_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('user_contracts', function (Blueprint $table) {
            $table->dropColumn('file_path');
        });
    }
};
