<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapping Jabatan -> (client Keycloak, role Keycloak) — "langkah 1->2" dari rantai role:
 * jabatan mana yang otomatis dapat role apa, di sistem (client) mana. Tabel konfigurasi
 * saja; PUSH aktual ke Keycloak (assign/lepas role user) dilakukan terpisah lewat
 * App\Services\Keycloak\KeycloakAdmin::assignClientRoleToUser()/removeClientRoleFromUser().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keycloak_role_mappings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('job_position_id')->constrained('m_job_positions')->cascadeOnDelete();
            // uuid internal client Keycloak (dipakai panggil API) — bukan 'clientId' (nama teknis).
            $table->string('keycloak_client_uuid');
            // Cache label buat tampilan (clientId Keycloak), biar tidak perlu API call tiap render tabel.
            $table->string('keycloak_client_label');
            $table->string('keycloak_role_name');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['job_position_id', 'keycloak_client_uuid', 'keycloak_role_name'],
                'keycloak_role_mappings_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keycloak_role_mappings');
    }
};
