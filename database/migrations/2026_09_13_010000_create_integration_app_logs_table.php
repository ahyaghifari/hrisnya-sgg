<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log tiap panggilan ke GET /api/integrasi/karyawan/{nip} — dipakai buat pastikan sistem
 * client BENERAN menjangkau HRIS (bukan cuma teori), dan buat debug kalau ada yang bilang
 * "provisioning tidak jalan" tapi tidak jelas request-nya nyampe atau tidak. Lihat
 * App\Models\Integrasi\IntegrationAppLog & App\Http\Controllers\Api\Integrasi\EmployeeDirectoryController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_app_logs', function (Blueprint $table) {
            $table->id();
            // Nullable: kalau token salah/tidak ada, kita belum tau ini aplikasi mana.
            $table->foreignUlid('integration_app_id')->nullable()->constrained('integration_apps')->nullOnDelete();
            $table->string('nip')->nullable();
            $table->string('status'); // unauthorized | not_found | success
            $table->string('ip', 64)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_app_logs');
    }
};
