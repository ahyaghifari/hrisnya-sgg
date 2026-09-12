<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registrasi sistem eksternal yang mengakses API integrasi HRIS (mis.
 * GET /api/integrasi/karyawan/{nip}) — satu baris = satu aplikasi konsumen,
 * masing-masing dengan token sendiri. Menggantikan kredensial tunggal
 * services.integrasi.user/password — lihat App\Models\Integrasi\IntegrationApp
 * & App\Filament\Keycloak\Resources\IntegrationApps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_apps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            // sha256 dari token — token asli (plaintext) hanya ditampilkan sekali saat
            // dibuat/di-generate ulang, tidak pernah disimpan/ditampilkan lagi setelah itu.
            $table->string('token_hash')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_apps');
    }
};
