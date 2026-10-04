<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran identitas orang (KTP, KK, STR, SIP, RKK) - SALINAN dari user_profile_documents
 * (roadmap_migrasi_bertahap_identity_hris.md). `file_path` sama persis dengan baris asal: file
 * terenkripsi di disk privat TIDAK dipindah/disalin, dan route lampiran lama tetap sumbernya.
 * `type` string biasa (bukan enum) karena label lama dulu bebas ketik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('type');
            $table->string('file_path');
            $table->timestamps();

            $table->unique(['person_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_documents');
    }
};
